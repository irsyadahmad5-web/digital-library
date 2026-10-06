<?php

namespace App\Modules\Library\Application\Pdf;

use App\Modules\Library\Application\Storage\ExternalPdfVerifier;
use App\Modules\Library\Application\Storage\ExternalUrlGuard;
use App\Modules\Library\Application\Storage\UploadPolicy;
use App\Modules\Settings\Application\SettingsManager;
use DomainException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ExternalPdfMaterializer
{
    public function __construct(
        private readonly ExternalPdfVerifier $verifier,
        private readonly ExternalUrlGuard $guard,
        private readonly SettingsManager $settings,
        private readonly UploadPolicy $policy,
    ) {}

    /**
     * @return array{path: string, size_bytes: int, sha256: string, mime_type: string}
     */
    public function materialize(string $url): array
    {
        $verified = $this->verifier->verify($url);
        $safeUrl = (string) $verified['external_url'];

        $disk = Storage::disk('local');
        $relative = 'pdf-processing-temp/'.Str::uuid().'.pdf';
        $absolute = $disk->path($relative);
        $disk->makeDirectory('pdf-processing-temp');

        try {
            $this->downloadFollowingRedirects($safeUrl, $absolute);

            if (! is_file($absolute)) {
                throw new DomainException('Temporary PDF eksternal tidak ditemukan.');
            }

            $size = (int) filesize($absolute);

            if ($size < 5 || $size > $this->policy->maxPdfBytes()) {
                throw new DomainException('Ukuran PDF eksternal hasil download tidak diizinkan.');
            }

            $handle = fopen($absolute, 'rb');

            if ($handle === false) {
                throw new DomainException('Temporary PDF eksternal tidak dapat dibaca.');
            }

            try {
                $signature = fread($handle, 5);
            } finally {
                fclose($handle);
            }

            if ($signature !== '%PDF-') {
                throw new DomainException('Konten external source bukan PDF yang valid.');
            }

            $sha256 = hash_file('sha256', $absolute);

            if (! is_string($sha256)) {
                throw new DomainException('Checksum PDF eksternal gagal dihitung.');
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $absolute) : null;

            if ($finfo) {
                finfo_close($finfo);
            }

            $mime = is_string($mime) && $mime !== ''
                ? $mime
                : 'application/pdf';

            if (! in_array($mime, ['application/pdf', 'application/octet-stream'], true)) {
                throw new DomainException('MIME external source tidak dikenali sebagai PDF.');
            }

            return [
                'path' => $relative,
                'size_bytes' => $size,
                'sha256' => $sha256,
                'mime_type' => 'application/pdf',
            ];
        } catch (Throwable $exception) {
            $disk->delete($relative);

            throw $exception;
        }
    }

    private function downloadFollowingRedirects(string $url, string $absolutePath): void
    {
        $timeout = max(
            5,
            min(
                120,
                (int) $this->settings->get('storage', 'external_timeout_seconds') * 4,
            ),
        );
        $httpsOnly = (bool) $this->settings->get('storage', 'https_only_external');
        $maxBytes = $this->policy->maxPdfBytes();

        for ($redirects = 0; $redirects <= 3; $redirects++) {
            $target = $this->guard->requestOptions($url, $httpsOnly);
            $url = $target['url'];

            $response = Http::timeout($timeout)
                ->connectTimeout(min(10, $timeout))
                ->withOptions(array_replace_recursive([
                    'allow_redirects' => false,
                    'stream' => true,
                    'sink' => $absolutePath,
                    'progress' => static function (
                        int $downloadTotal,
                        int $downloadedBytes,
                    ) use ($maxBytes): void {
                        if (
                            ($downloadTotal > 0 && $downloadTotal > $maxBytes)
                            || $downloadedBytes > $maxBytes
                        ) {
                            throw new DomainException(
                                'PDF eksternal melebihi batas maksimum saat download.',
                            );
                        }
                    },
                ], $target['options']))
                ->withHeaders([
                    'Accept' => 'application/pdf',
                ])
                ->get($url);

            $status = $response->status();

            if (in_array($status, [301, 302, 303, 307, 308], true)) {
                $location = $response->header('Location');
                $this->closeBody($response);

                if (! is_string($location) || trim($location) === '') {
                    throw new DomainException('Redirect external source tidak memiliki tujuan valid.');
                }

                @unlink($absolutePath);
                $url = $this->resolveRedirectUrl($url, trim($location));

                continue;
            }

            if ($status < 200 || $status >= 300) {
                $this->closeBody($response);

                throw new DomainException("External source gagal di-download. HTTP {$status}.");
            }

            $this->closeBody($response);

            return;
        }

        throw new DomainException('External source memiliki terlalu banyak redirect.');
    }

    private function resolveRedirectUrl(string $baseUrl, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $base = parse_url($baseUrl);

        if (! is_array($base) || empty($base['scheme']) || empty($base['host'])) {
            throw new DomainException('Redirect relatif external source tidak dapat diselesaikan.');
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

    private function closeBody(Response $response): void
    {
        try {
            $response->toPsrResponse()->getBody()->close();
        } catch (Throwable) {
            // Best-effort stream cleanup.
        }
    }
}
