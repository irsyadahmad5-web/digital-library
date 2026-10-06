<?php

namespace App\Modules\Library\Application\Reader;

use App\Modules\Library\Application\Storage\ExternalUrlGuard;
use App\Modules\Library\Application\Storage\PrivateEbookPathGuard;
use App\Modules\Library\Application\Storage\UploadPolicy;
use App\Modules\Library\Domain\Models\EbookFile;
use App\Modules\Settings\Application\SettingsManager;
use DomainException;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PdfSourceStreamer
{
    private const STREAM_CHUNK_BYTES = 262144;

    public function __construct(
        private readonly ExternalUrlGuard $guard,
        private readonly PrivateEbookPathGuard $pathGuard,
        private readonly SettingsManager $settings,
        private readonly UploadPolicy $policy,
    ) {}

    public function stream(EbookFile $file, Request $request): Response
    {
        return $this->streamSource($file, $request, 'inline', 'ebook.pdf');
    }

    public function download(
        EbookFile $file,
        Request $request,
        string $filename,
    ): Response {
        return $this->streamSource($file, $request, 'attachment', $filename);
    }

    private function streamSource(
        EbookFile $file,
        Request $request,
        string $disposition,
        string $filename,
    ): Response {
        return match ($file->source_type) {
            'local' => $this->streamLocal($file, $request, $disposition, $filename),
            'external_url' => $this->streamExternal($file, $request, $disposition, $filename),
            default => abort(404),
        };
    }

    private function streamLocal(
        EbookFile $file,
        Request $request,
        string $disposition,
        string $filename,
    ): Response {
        if ($file->disk !== 'local' || ! is_string($file->path) || $file->path === '') {
            abort(404);
        }

        try {
            $path = $this->pathGuard->assertAllowed(
                $file->ebook_id,
                $file->path,
            );
        } catch (DomainException) {
            abort(404);
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            abort(404);
        }

        $absolutePath = $disk->path($path);

        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            abort(404);
        }

        $size = (int) filesize($absolutePath);

        if ($size <= 0) {
            abort(404);
        }

        $headers = $this->baseHeaders(
            $this->etag($file, $absolutePath),
            $disposition,
            $filename,
        );
        $mtime = filemtime($absolutePath);

        if ($mtime !== false) {
            $headers['Last-Modified'] = gmdate('D, d M Y H:i:s', $mtime).' GMT';
        }

        $rangeHeader = $request->header('Range');

        if (
            is_string($rangeHeader)
            && trim($rangeHeader) !== ''
            && ! $this->ifRangeMatches(
                $request,
                $headers['ETag'] ?? null,
                $mtime === false ? null : $mtime,
            )
        ) {
            $rangeHeader = null;
        }

        if (
            (! is_string($rangeHeader) || trim($rangeHeader) === '')
            && $this->isNotModified(
                $request,
                $headers['ETag'] ?? null,
                $mtime === false ? null : $mtime,
            )
        ) {
            return new Response('', 304, $headers);
        }

        try {
            $range = $this->parseRange($rangeHeader, $size);
        } catch (DomainException) {
            return $this->rangeNotSatisfiable($size);
        }

        $start = $range['start'] ?? 0;
        $end = $range['end'] ?? ($size - 1);
        $length = ($end - $start) + 1;
        $status = $range === null ? 200 : 206;
        $headers['Content-Length'] = (string) $length;

        if ($range !== null) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        if ($request->isMethod('HEAD')) {
            return new Response('', $status, $headers);
        }

        return new StreamedResponse(
            static function () use ($absolutePath, $start, $length): void {
                $handle = fopen($absolutePath, 'rb');

                if ($handle === false) {
                    return;
                }

                try {
                    if ($start > 0) {
                        fseek($handle, $start);
                    }

                    $remaining = $length;

                    while ($remaining > 0 && ! feof($handle)) {
                        $readLength = min(self::STREAM_CHUNK_BYTES, $remaining);
                        $chunk = fread($handle, $readLength);

                        if ($chunk === false || $chunk === '') {
                            break;
                        }

                        echo $chunk;
                        $remaining -= strlen($chunk);

                        if (connection_aborted()) {
                            break;
                        }
                    }
                } finally {
                    fclose($handle);
                }
            },
            $status,
            $headers,
        );
    }

    private function streamExternal(
        EbookFile $file,
        Request $request,
        string $disposition,
        string $filename,
    ): Response {
        $url = trim((string) $file->external_url);

        if ($url === '') {
            abort(404);
        }

        $rangeHeader = $request->header('Range');
        $knownSize = $file->size_bytes !== null && $file->size_bytes > 0
            ? (int) $file->size_bytes
            : null;

        if (is_string($rangeHeader) && trim($rangeHeader) !== '') {
            try {
                if ($knownSize !== null) {
                    $range = $this->parseRange($rangeHeader, $knownSize);

                    if ($range !== null) {
                        $rangeHeader = "bytes={$range['start']}-{$range['end']}";
                    }
                } else {
                    $this->assertRangeSyntax($rangeHeader);
                }
            } catch (DomainException) {
                return $this->rangeNotSatisfiable($knownSize);
            }
        } else {
            $rangeHeader = null;
        }

        try {
            [$upstream] = $this->requestExternal(
                $request->isMethod('HEAD') ? 'HEAD' : 'GET',
                $url,
                $rangeHeader,
            );
        } catch (Throwable) {
            return new Response(
                'Sumber PDF eksternal sedang tidak tersedia.',
                502,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            );
        }

        $status = $upstream->status();

        if ($status === 416) {
            $size = $this->totalSizeFromContentRange($upstream) ?? $knownSize;
            $this->closeBody($upstream);

            return $this->rangeNotSatisfiable($size);
        }

        if (! in_array($status, [200, 206], true)) {
            $this->closeBody($upstream);

            return new Response(
                'Sumber PDF eksternal sedang tidak tersedia.',
                502,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            );
        }

        $contentLength = $this->numericHeader($upstream, 'Content-Length');
        $totalSize = $this->totalSizeFromContentRange($upstream)
            ?? ($status === 200 ? $contentLength : $knownSize);

        if ($totalSize !== null && $totalSize > $this->policy->maxPdfBytes()) {
            $this->closeBody($upstream);

            return new Response(
                'Ukuran sumber PDF melebihi batas yang diizinkan.',
                502,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            );
        }

        $headers = $this->baseHeaders(
            $upstream->header('ETag') ?: $file->etag,
            $disposition,
            $filename,
        );

        foreach (['Content-Length', 'Content-Range', 'Last-Modified'] as $header) {
            $value = $upstream->header($header);

            if (is_string($value) && $value !== '') {
                $headers[$header] = $value;
            }
        }

        $acceptRanges = $upstream->header('Accept-Ranges');

        if (is_string($acceptRanges) && $acceptRanges !== '') {
            $headers['Accept-Ranges'] = $acceptRanges;
        }

        if ($request->isMethod('HEAD')) {
            $this->closeBody($upstream);

            return new Response('', $status, $headers);
        }

        $body = $upstream->toPsrResponse()->getBody();
        $maxBytes = $this->policy->maxPdfBytes();

        return new StreamedResponse(
            static function () use ($body, $maxBytes): void {
                $streamed = 0;

                try {
                    while (! $body->eof()) {
                        $chunk = $body->read(self::STREAM_CHUNK_BYTES);

                        if ($chunk === '') {
                            break;
                        }

                        $streamed += strlen($chunk);

                        if ($streamed > $maxBytes) {
                            break;
                        }

                        echo $chunk;

                        if (connection_aborted()) {
                            break;
                        }
                    }
                } finally {
                    $body->close();
                }
            },
            $status,
            $headers,
        );
    }

    /**
     * @return array{0: HttpResponse, 1: string}
     */
    private function requestExternal(
        string $method,
        string $url,
        ?string $rangeHeader,
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
            $headers = ['Accept' => 'application/pdf'];

            if ($rangeHeader !== null) {
                $headers['Range'] = $rangeHeader;
            }

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
                throw new DomainException('Redirect sumber PDF tidak valid.');
            }

            $url = $this->resolveRedirectUrl($url, trim($location));
        }

        throw new DomainException('Sumber PDF memiliki terlalu banyak redirect.');
    }

    /**
     * @return array{start: int, end: int}|null
     */
    private function parseRange(?string $header, int $size): ?array
    {
        if (! is_string($header) || trim($header) === '') {
            return null;
        }

        $header = trim($header);
        $this->assertRangeSyntax($header);

        preg_match('/^bytes=(\d*)-(\d*)$/', $header, $matches);

        $rawStart = $matches[1] ?? '';
        $rawEnd = $matches[2] ?? '';

        if ($rawStart === '') {
            $suffixLength = (int) $rawEnd;

            if ($suffixLength <= 0) {
                throw new DomainException('Range tidak valid.');
            }

            $suffixLength = min($suffixLength, $size);

            return [
                'start' => $size - $suffixLength,
                'end' => $size - 1,
            ];
        }

        $start = (int) $rawStart;

        if ($start >= $size) {
            throw new DomainException('Range di luar ukuran file.');
        }

        $end = $rawEnd === ''
            ? $size - 1
            : min((int) $rawEnd, $size - 1);

        if ($end < $start) {
            throw new DomainException('Range tidak valid.');
        }

        return compact('start', 'end');
    }

    private function assertRangeSyntax(string $header): void
    {
        if (
            str_contains($header, ',')
            || ! preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $matches)
            || (($matches[1] ?? '') === '' && ($matches[2] ?? '') === '')
        ) {
            throw new DomainException('Range tidak valid.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function baseHeaders(
        ?string $etag,
        string $disposition,
        string $filename,
    ): array {
        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $this->contentDisposition($disposition, $filename),
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ];

        if (is_string($etag) && trim($etag) !== '') {
            $etag = trim($etag);

            if (preg_match('/^(?:W\\/)?"[^"]+"$/', $etag)) {
                $headers['ETag'] = $etag;
            } else {
                $etag = preg_replace('/[^A-Za-z0-9._:-]/', '', $etag) ?? '';

                if ($etag !== '') {
                    $headers['ETag'] = '"'.$etag.'"';
                }
            }
        }

        return $headers;
    }

    private function contentDisposition(string $disposition, string $filename): string
    {
        $disposition = $disposition === 'attachment' ? 'attachment' : 'inline';
        $filename = trim(str_replace(
            ["\r", "\n", '\\'],
            ['', '', '-'],
            basename($filename),
        ));

        if ($filename === '') {
            $filename = 'ebook.pdf';
        }

        if (! str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }

        $fallback = Str::ascii($filename);
        $fallback = preg_replace('/[^\x20-\x7E]/', '', $fallback) ?: 'ebook.pdf';
        $fallback = str_replace(['%', '/', '\\'], '-', $fallback);

        return HeaderUtils::makeDisposition(
            $disposition,
            $filename,
            $fallback,
        );
    }

    private function etag(EbookFile $file, string $absolutePath): ?string
    {
        if (is_string($file->sha256) && $file->sha256 !== '') {
            return $file->sha256;
        }

        $mtime = filemtime($absolutePath);
        $size = filesize($absolutePath);

        return $mtime !== false && $size !== false
            ? sha1($absolutePath.'|'.$mtime.'|'.$size)
            : null;
    }

    private function isNotModified(
        Request $request,
        ?string $etag,
        ?int $mtime,
    ): bool {
        $ifNoneMatch = trim((string) $request->header('If-None-Match'));

        if ($ifNoneMatch !== '') {
            if ($ifNoneMatch === '*') {
                return true;
            }

            if ($etag === null || $etag === '') {
                return false;
            }

            $normalized = preg_replace('/^W\//i', '', trim($etag));

            foreach (explode(',', $ifNoneMatch) as $candidate) {
                $candidate = preg_replace('/^W\//i', '', trim($candidate));

                if ($candidate !== '' && hash_equals($normalized, $candidate)) {
                    return true;
                }
            }

            return false;
        }

        $ifModifiedSince = trim((string) $request->header('If-Modified-Since'));

        if ($mtime === null || $ifModifiedSince === '') {
            return false;
        }

        $since = strtotime($ifModifiedSince);

        return $since !== false && $mtime <= $since;
    }

    private function ifRangeMatches(
        Request $request,
        ?string $etag,
        ?int $mtime,
    ): bool {
        $ifRange = trim((string) $request->header('If-Range'));

        if ($ifRange === '') {
            return true;
        }

        if (str_starts_with($ifRange, '"') || str_starts_with($ifRange, 'W/')) {
            return $etag !== null
                && ! str_starts_with($ifRange, 'W/')
                && ! str_starts_with($etag, 'W/')
                && hash_equals(trim($etag), $ifRange);
        }

        if ($mtime === null) {
            return false;
        }

        $since = strtotime($ifRange);

        return $since !== false && $mtime <= $since;
    }

    private function rangeNotSatisfiable(?int $size): Response
    {
        $headers = [
            'Accept-Ranges' => 'bytes',
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ];

        if ($size !== null && $size >= 0) {
            $headers['Content-Range'] = 'bytes */'.$size;
        }

        return new Response('', 416, $headers);
    }

    private function numericHeader(HttpResponse $response, string $header): ?int
    {
        $value = $response->header($header);

        return is_numeric($value) ? (int) $value : null;
    }

    private function totalSizeFromContentRange(HttpResponse $response): ?int
    {
        $value = (string) $response->header('Content-Range');

        if (preg_match('#^bytes\s+(?:\d+-\d+|\*)/(\d+)$#i', $value, $matches)) {
            return (int) $matches[1];
        }

        return null;
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

    private function closeBody(HttpResponse $response): void
    {
        try {
            $response->toPsrResponse()->getBody()->close();
        } catch (Throwable) {
            // Best-effort stream cleanup.
        }
    }
}
