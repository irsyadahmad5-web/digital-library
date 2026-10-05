<?php

namespace App\Modules\Seo\Application;

use App\Modules\Library\Application\PublicLibrary\PublicLibraryCache;
use App\Modules\Library\Domain\Models\Author;
use App\Modules\Library\Domain\Models\Category;
use App\Modules\Library\Domain\Models\Collection;
use App\Modules\Library\Domain\Models\Ebook;
use App\Modules\Library\Domain\Models\Publisher;
use App\Modules\Settings\Application\SettingsManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SitemapBuilder
{
    public function __construct(
        private readonly SeoManager $seo,
        private readonly SettingsManager $settings,
        private readonly PublicLibraryCache $cache,
    ) {}

    public function xml(Request $request): string
    {
        $entries = $this->entries($request);

        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($entries as $entry) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.$this->escape($entry['loc']).'</loc>';

            if ($entry['lastmod'] !== null) {
                $lines[] = '    <lastmod>'.$this->escape($entry['lastmod']).'</lastmod>';
            }

            if ($entry['changefreq'] !== null) {
                $lines[] = '    <changefreq>'.$entry['changefreq'].'</changefreq>';
            }

            if ($entry['priority'] !== null) {
                $lines[] = '    <priority>'.$entry['priority'].'</priority>';
            }

            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }

    public function robots(Request $request): string
    {
        $indexing = (bool) $this->settings->get('seo', 'robots_index');

        if (! $indexing) {
            return implode("\n", [
                'User-agent: *',
                'Disallow: /',
                '',
                'Sitemap: '.$this->seo->canonical($request, '/sitemap.xml'),
                '',
            ]);
        }

        return implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /read/',
            'Disallow: /search',
            'Disallow: /*/download',
            '',
            'Sitemap: '.$this->seo->canonical($request, '/sitemap.xml'),
            '',
        ]);
    }

    /**
     * @return array<int, array{loc: string, lastmod: ?string, changefreq: ?string, priority: ?string}>
     */
    public function entries(Request $request): array
    {
        return $this->cache->remember(
            'seo:sitemap:entries:'.$this->seo->baseUrl($request),
            300,
            fn (): array => $this->buildEntries($request),
        );
    }

    /**
     * @return array<int, array{loc: string, lastmod: ?string, changefreq: ?string, priority: ?string}>
     */
    private function buildEntries(Request $request): array
    {
        $entries = [];

        foreach ([
            ['/', null, 'daily', '1.0'],
            ['/library', null, 'daily', '0.9'],
            ['/categories', null, 'weekly', '0.7'],
            ['/authors', null, 'weekly', '0.7'],
            ['/publishers', null, 'weekly', '0.6'],
            ['/collections', null, 'weekly', '0.7'],
            ['/about', null, 'monthly', '0.5'],
            ['/contact', null, 'monthly', '0.4'],
        ] as [$path, $lastmod, $changefreq, $priority]) {
            $entries[] = $this->entry(
                $request,
                $path,
                $lastmod,
                $changefreq,
                $priority,
            );
        }

        Ebook::query()
            ->publiclyVisible()
            ->select(['id', 'slug', 'updated_at', 'published_at'])
            ->orderBy('id')
            ->chunkById(500, function ($ebooks) use (&$entries, $request): void {
                foreach ($ebooks as $ebook) {
                    $lastmod = $ebook->updated_at?->toDateString()
                        ?? $ebook->published_at?->toDateString();

                    $entries[] = $this->entry(
                        $request,
                        '/book/'.$ebook->slug,
                        $lastmod,
                        'monthly',
                        '0.8',
                    );
                }
            });

        $this->appendTaxonomy(
            $entries,
            $request,
            Category::query(),
            '/category/',
        );
        $this->appendTaxonomy(
            $entries,
            $request,
            Author::query(),
            '/author/',
        );
        $this->appendTaxonomy(
            $entries,
            $request,
            Publisher::query(),
            '/publisher/',
        );
        $this->appendTaxonomy(
            $entries,
            $request,
            Collection::query(),
            '/collection/',
        );

        return array_slice($entries, 0, 50000);
    }

    /**
     * @param  array<int, array{loc: string, lastmod: ?string, changefreq: ?string, priority: ?string}>  $entries
     */
    private function appendTaxonomy(
        array &$entries,
        Request $request,
        Builder $query,
        string $pathPrefix,
    ): void {
        $query
            ->where('is_active', true)
            ->whereHas(
                'ebooks',
                fn (Builder $ebooks): Builder => $ebooks->publiclyVisible(),
            )
            ->select(['id', 'slug', 'updated_at'])
            ->orderBy('id')
            ->chunkById(500, function ($items) use (
                &$entries,
                $request,
                $pathPrefix,
            ): void {
                foreach ($items as $item) {
                    $entries[] = $this->entry(
                        $request,
                        $pathPrefix.$item->slug,
                        $item->updated_at?->toDateString(),
                        'weekly',
                        '0.6',
                    );
                }
            });
    }

    /**
     * @return array{loc: string, lastmod: ?string, changefreq: ?string, priority: ?string}
     */
    private function entry(
        Request $request,
        string $path,
        ?string $lastmod,
        ?string $changefreq,
        ?string $priority,
    ): array {
        return [
            'loc' => $this->seo->canonical($request, $path),
            'lastmod' => $lastmod,
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];
    }

    private function escape(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_XML1,
            'UTF-8',
        );
    }
}
