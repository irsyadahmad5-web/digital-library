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
        return new Response(
            $this->sitemap->xml($request),
            200,
            [
                'Content-Type' => 'application/xml; charset=UTF-8',
                'Cache-Control' => 'public, max-age=3600',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function robots(Request $request): Response
    {
        return new Response(
            $this->sitemap->robots($request),
            200,
            [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'public, max-age=3600',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
