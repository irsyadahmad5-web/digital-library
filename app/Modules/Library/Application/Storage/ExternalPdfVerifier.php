<?php

namespace App\Modules\Library\Application\Storage;

use App\Modules\Settings\Application\SettingsManager;
use DomainException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ExternalPdfVerifier
{
    public function __construct(
        private readonly ExternalUrlGuard $guard,
        private readonly SettingsManager $settings,
        private readonly UploadPolicy $policy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function verify(string $url): array
    {
        $httpsOnly = (bool) $this->settings->get('storage', 'https_only_external');
        $url = $this->guard->assertAllowed($url, $httpsOnly);

        if (! (bool) $this->settings->get('storage', 'verify_external_urls')) {
            return [
                'external_url' => $url,
                'mime_type' => null,
                'size_bytes' => null,
                'etag' => null,
                'last_modified' => null,
                'verification_status' => 'skipped',
                'verified_at' => null,
                'last_checked_at' => now(),
            ];
        }

        [$head, $headUrl] = $this->requestFollowingRedirects('HEAD', $url);

        $headSize = $this->contentLength($head);
        $this->assertSizeAllowed($headSize);

        [$range, $finalUrl] = $this->requestFollowingRedirects(
            'GET',
            $headUrl,
            ['Range' => 'bytes=0-4', 'Accept' => 'application/pdf'],
        );

        if (! in_array($range->status(), [200, 206], true)) {
            $this->closeBody($range);

            throw new DomainException(
                'URL eksternal tidak dapat dibaca sebagai PDF. HTTP '.$range->status().'.',
            );
        }

        $rangeSize = $this->totalSizeFromRange($range)
            ?? ($range->status() === 200 ? $this->contentLength($range) : null);
        $size = $headSize ?? $rangeSize;

        $this->assertSizeAllowed($size);

        $stream = $range->toPsrResponse()->getBody();
        $prefix = $stream->read(5);
        $stream->close();

        if ($prefix !== '%PDF-') {
            throw new DomainException('Konten URL eksternal bukan file PDF yang valid.');
        }

        $mime = $this->normalizedContentType(
            $range->header('Content-Type')
                ?: $head->header('Content-Type'),
        );

        $this->closeBody($head);

        return [
            'external_url' => $finalUrl,
            'mime_type' => $mime ?: 'application/pdf',
            'size_bytes' => $size,
            'etag' => $range->header('ETag') ?: $head->header('ETag'),
            'last_modified' => $range->header('Last-Modified') ?: $head->header('Last-Modified'),
            'verification_status' => 'verified',
            'verified_at' => now(),
            'last_checked_at' => now(),
        ];
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{0: Response, 1: string}
     */
    private function requestFollowingRedirects(
        string $method,
        string $url,
        array $headers = [],
    ): array {
        $timeout = max(
            3,
            min(
                30,
                (int) $this->settings->get('storage', 'external_timeout_seconds'),
            ),
        );
        $httpsOnly = (bool) $this->settings->get('storage', 'https_only_external');

        for ($redirects = 0; $redirects <= 3; $redirects++) {
            $target = $this->guard->requestOptions($url, $httpsOnly);
            $url = $target['url'];

            $pending = Http::timeout($timeout)
                ->connectTimeout(min(5, $timeout))
                ->withOptions(array_replace_recursive([
                    'allow_redirects' => false,
                    'stream' => true,
                ], $target['options']))
                ->withHeaders($headers);

            $response = strtoupper($method) === 'HEAD'
                ? $pending->head($url)
                : $pending->get($url);

            if (! in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                return [$response, $url];
            }

            $location = $response->header('Location');
            $this->closeBody($response);

            if (! is_string($location) || trim($location) === '') {
                throw new DomainException('Redirect URL eksternal tidak memiliki tujuan yang valid.');
            }

            $url = $this->resolveRedirectUrl($url, trim($location));
        }

        throw new DomainException('URL eksternal memiliki terlalu banyak redirect.');
    }

    private function resolveRedirectUrl(string $baseUrl, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $base = parse_url($baseUrl);

        if (! is_array($base) || empty($base['scheme']) || empty($base['host'])) {
            throw new DomainException('Redirect relatif tidak dapat diselesaikan.');
        }

        $origin = $base['scheme'].'://'.$base['host'];

        if (isset($base['port'])) {
            $origin .= ':'.$base['port'];
        }

        if (str_starts_with($location, '//')) {
            return $base['scheme'].':'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $path = (string) ($base['path'] ?? '/');
        $directory = rtrim(str_replace('\\', '/', dirname($path)), '/');

        return $origin.($directory === '' ? '' : $directory).'/'.$location;
    }

    private function contentLength(Response $response): ?int
    {
        $value = $response->header('Content-Length');

        return is_numeric($value) ? (int) $value : null;
    }

    private function totalSizeFromRange(Response $response): ?int
    {
        $value = (string) $response->header('Content-Range');

        if (preg_match('#^bytes\s+\d+-\d+/(\d+)$#i', $value, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function assertSizeAllowed(?int $size): void
    {
        if ($size !== null && $size > $this->policy->maxPdfBytes()) {
            throw new DomainException('Ukuran PDF eksternal melebihi batas maksimum yang diizinkan.');
        }
    }

    private function normalizedContentType(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return strtolower(trim(explode(';', $value, 2)[0]));
    }

    private function closeBody(Response $response): void
    {
        try {
            $response->toPsrResponse()->getBody()->close();
        } catch (\Throwable) {
            // Stream cleanup is best-effort.
        }
    }
}
