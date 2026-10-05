<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Seo\Application\SitemapBuilder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SeoController extends Controller
{
    public function __construct(
        private readonly SitemapBuilder $sitemap,
    ) {}

    public function sitemap(Request $request): Response
    {
        return $this->cacheableTextResponse(
            $request,
            $this->sitemap->xml($request),
            'application/xml; charset=UTF-8',
        );
    }

    public function robots(Request $request): Response
    {
        return $this->cacheableTextResponse(
            $request,
            $this->sitemap->robots($request),
            'text/plain; charset=UTF-8',
        );
    }

    private function cacheableTextResponse(
        Request $request,
        string $content,
        string $contentType,
    ): Response {
        $etag = '"'.hash('sha256', $content).'"';
        $headers = [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=3600, stale-while-revalidate=300',
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($this->etagMatches($request, $etag)) {
            return new Response('', 304, $headers);
        }

        return new Response($content, 200, $headers);
    }

    private function etagMatches(Request $request, string $etag): bool
    {
        $header = trim((string) $request->header('If-None-Match'));

        if ($header === '') {
            return false;
        }

        if ($header === '*') {
            return true;
        }

        $normalized = preg_replace('/^W\//i', '', $etag);

        foreach (explode(',', $header) as $candidate) {
            $candidate = preg_replace('/^W\//i', '', trim($candidate));

            if ($candidate !== '' && hash_equals($normalized, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
