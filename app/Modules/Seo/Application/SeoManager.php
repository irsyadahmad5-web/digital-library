<?php

namespace App\Modules\Seo\Application;

use App\Modules\Library\Domain\Models\Ebook;
use App\Modules\Settings\Application\SettingsManager;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SeoManager
{
    public function __construct(
        private readonly SettingsManager $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function page(
        Request $request,
        ?string $title = null,
        ?string $description = null,
        array $options = [],
    ): array {
        $site = $this->settings->public();
        $general = $site['general'] ?? [];
        $seo = $site['seo'] ?? [];

        $siteName = trim((string) ($general['site_name'] ?? 'Digital Library'));
        $suffix = trim((string) ($seo['title_suffix'] ?? $siteName));
        $pageTitle = $this->title($title, $suffix ?: $siteName);
        $description = $this->description(
            $description,
            (string) ($seo['meta_description'] ?? $general['description'] ?? ''),
        );

        $globalIndexing = (bool) ($seo['robots_index'] ?? true);
        $indexAllowed = $globalIndexing
            && (bool) ($options['index'] ?? true);
        $followAllowed = $globalIndexing
            && (bool) ($options['follow'] ?? true);

        $canonicalPath = (string) ($options['canonical_path'] ?? $request->getPathInfo());
        $keepPage = (bool) ($options['keep_page'] ?? false);
        $pageNumber = filter_var($request->query('page'), FILTER_VALIDATE_INT);

        if ($keepPage && $pageNumber !== false && $pageNumber > 1) {
            $baseTitle = trim((string) $title);
            $pageTitle = $this->title(
                ($baseTitle !== '' ? $baseTitle.' — ' : '').'Halaman '.$pageNumber,
                $suffix ?: $siteName,
            );
        }

        $canonical = $this->canonical($request, $canonicalPath, $keepPage);

        $image = $this->absoluteUrl(
            $request,
            $options['image'] ?? ($general['logo_url'] ?? null),
        );

        $ogTitle = trim((string) ($options['og_title'] ?? $pageTitle));
        $ogDescription = $this->description(
            $options['og_description'] ?? null,
            $description,
        );

        return [
            'title' => $pageTitle,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => ($indexAllowed ? 'index' : 'noindex').','.($followAllowed ? 'follow' : 'nofollow'),
            'google_site_verification' => trim((string) ($seo['google_site_verification'] ?? '')),
            'open_graph' => [
                'type' => (string) ($options['og_type'] ?? 'website'),
                'locale' => 'id_ID',
                'site_name' => $siteName,
                'title' => $ogTitle !== '' ? $ogTitle : $pageTitle,
                'description' => $ogDescription,
                'url' => $canonical,
                'image' => $image,
                'image_alt' => trim((string) ($options['image_alt'] ?? $ogTitle ?: $pageTitle)),
            ],
            'twitter' => [
                'card' => $image ? 'summary_large_image' : 'summary',
                'title' => $ogTitle !== '' ? $ogTitle : $pageTitle,
                'description' => $ogDescription,
                'image' => $image,
                'image_alt' => trim((string) ($options['image_alt'] ?? $ogTitle ?: $pageTitle)),
            ],
            'json_ld' => array_values(array_filter(
                $options['json_ld'] ?? [],
                fn (mixed $value): bool => is_array($value) && $value !== [],
            )),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function home(Request $request): array
    {
        $site = $this->settings->public();
        $general = $site['general'] ?? [];
        $seo = $site['seo'] ?? [];
        $siteName = trim((string) ($general['site_name'] ?? 'Digital Library'));
        $description = $this->description(
            $seo['meta_description'] ?? null,
            (string) ($general['description'] ?? ''),
        );
        $url = $this->canonical($request, '/');

        $graph = [[
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => $url.'#website',
            'url' => $url,
            'name' => $siteName,
            'description' => $description,
            'inLanguage' => 'id-ID',
        ]];

        $organization = trim((string) ($general['organization_name'] ?? ''));

        if ($organization !== '') {
            $organizationNode = [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                '@id' => $url.'#organization',
                'name' => $organization,
                'url' => $url,
            ];

            $logo = $this->absoluteUrl($request, $general['logo_url'] ?? null);

            if ($logo) {
                $organizationNode['logo'] = $logo;
            }

            $email = trim((string) ($general['email'] ?? ''));
            $phone = trim((string) ($general['phone'] ?? ''));

            if ($email !== '') {
                $organizationNode['email'] = $email;
            }

            if ($phone !== '') {
                $organizationNode['telephone'] = $phone;
            }

            $graph[] = $organizationNode;
        }

        return $this->page(
            $request,
            null,
            $description,
            [
                'canonical_path' => '/',
                'og_title' => trim((string) ($seo['og_title'] ?? $siteName)) ?: $siteName,
                'og_description' => trim((string) ($seo['og_description'] ?? $description)) ?: $description,
                'json_ld' => $graph,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    public function book(Request $request, Ebook $ebook, array $detail): array
    {
        $canonicalPath = '/book/'.$ebook->slug;
        $canonical = $this->canonical($request, $canonicalPath);
        $description = $this->description(
            $ebook->description,
            (string) ($ebook->subtitle ?? 'Baca ebook '.$ebook->title.' di perpustakaan digital.'),
        );

        $bookNode = [
            '@context' => 'https://schema.org',
            '@type' => 'Book',
            '@id' => $canonical.'#book',
            'url' => $canonical,
            'name' => $ebook->title,
            'description' => $description,
            'inLanguage' => $ebook->language?->code,
        ];

        if ($ebook->subtitle) {
            $bookNode['alternateName'] = $ebook->subtitle;
        }

        if ($ebook->isbn) {
            $bookNode['isbn'] = $ebook->isbn;
        }

        if ($ebook->publication_year) {
            $bookNode['datePublished'] = (string) $ebook->publication_year;
        }

        if ($ebook->page_count) {
            $bookNode['numberOfPages'] = (int) $ebook->page_count;
        }

        $cover = $this->absoluteUrl($request, $detail['cover_url'] ?? null);

        if ($cover) {
            $bookNode['image'] = $cover;
        }

        $authors = $ebook->authors
            ->where('is_active', true)
            ->map(fn ($author): array => [
                '@type' => 'Person',
                'name' => $author->name,
                'url' => $this->canonical($request, '/author/'.$author->slug),
            ])
            ->values()
            ->all();

        if ($authors !== []) {
            $bookNode['author'] = $authors;
        }

        if ($ebook->publisher?->is_active) {
            $bookNode['publisher'] = [
                '@type' => 'Organization',
                'name' => $ebook->publisher->name,
                'url' => $this->canonical($request, '/publisher/'.$ebook->publisher->slug),
            ];
        }

        $genres = $ebook->categories
            ->where('is_active', true)
            ->pluck('name')
            ->values()
            ->all();

        if ($genres !== []) {
            $bookNode['genre'] = $genres;
        }

        if ($ebook->collection?->is_active) {
            $bookNode['isPartOf'] = [
                '@type' => 'CollectionPage',
                'name' => $ebook->collection->name,
                'url' => $this->canonical($request, '/collection/'.$ebook->collection->slug),
            ];
        }

        $actions = [];

        if ($ebook->read_enabled) {
            $actions[] = [
                '@type' => 'ReadAction',
                'target' => $this->canonical($request, '/read/'.$ebook->slug),
            ];
        }

        if (
            $ebook->download_enabled
            && (bool) $this->settings->get('downloads', 'public_enabled')
        ) {
            $actions[] = [
                '@type' => 'DownloadAction',
                'target' => $this->canonical($request, '/book/'.$ebook->slug.'/download'),
            ];
        }

        if ($actions !== []) {
            $bookNode['potentialAction'] = $actions;
        }

        $breadcrumb = $this->breadcrumb($request, [
            ['name' => 'Beranda', 'path' => '/'],
            ['name' => 'Katalog', 'path' => '/library'],
            ['name' => $ebook->title, 'path' => $canonicalPath],
        ]);

        return $this->page(
            $request,
            $ebook->title,
            $description,
            [
                'canonical_path' => $canonicalPath,
                'og_type' => 'book',
                'image' => $cover,
                'image_alt' => 'Sampul '.$ebook->title,
                'json_ld' => [$bookNode, $breadcrumb],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $taxonomy
     * @return array<string, mixed>
     */
    public function taxonomy(
        Request $request,
        string $type,
        array $taxonomy,
        bool $hasNonCanonicalQuery,
    ): array {
        $labels = [
            'category' => 'Kategori',
            'author' => 'Penulis',
            'publisher' => 'Penerbit',
            'collection' => 'Koleksi',
        ];
        $directoryPaths = [
            'category' => '/categories',
            'author' => '/authors',
            'publisher' => '/publishers',
            'collection' => '/collections',
        ];

        $label = $labels[$type] ?? Str::headline($type);
        $path = '/'.$type.'/'.$taxonomy['slug'];
        $description = $this->description(
            $taxonomy['description'] ?? null,
            "Jelajahi ebook untuk {$label} {$taxonomy['name']}.",
        );

        $collectionPage = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            '@id' => $this->canonical($request, $path).'#collection',
            'url' => $this->canonical($request, $path),
            'name' => $taxonomy['name'],
            'description' => $description,
        ];

        $breadcrumb = $this->breadcrumb($request, [
            ['name' => 'Beranda', 'path' => '/'],
            ['name' => $label, 'path' => $directoryPaths[$type]],
            ['name' => $taxonomy['name'], 'path' => $path],
        ]);

        return $this->page(
            $request,
            $taxonomy['name'].' · '.$label,
            $description,
            [
                'canonical_path' => $path,
                'keep_page' => ! $hasNonCanonicalQuery,
                'index' => ! $hasNonCanonicalQuery,
                'json_ld' => [$collectionPage, $breadcrumb],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function directory(Request $request, string $title, string $type): array
    {
        $description = match ($type) {
            'category' => 'Jelajahi ebook berdasarkan kategori dan subkategori.',
            'author' => 'Temukan ebook dan karya berdasarkan penulis.',
            'publisher' => 'Jelajahi ebook berdasarkan penerbit.',
            'collection' => 'Temukan ebook yang dikelompokkan dalam koleksi.',
            default => 'Jelajahi koleksi perpustakaan digital.',
        };

        $path = match ($type) {
            'category' => '/categories',
            'author' => '/authors',
            'publisher' => '/publishers',
            'collection' => '/collections',
            default => $request->getPathInfo(),
        };

        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            '@id' => $this->canonical($request, $path).'#directory',
            'url' => $this->canonical($request, $path),
            'name' => $title,
            'description' => $description,
        ];

        return $this->page(
            $request,
            $title,
            $description,
            [
                'canonical_path' => $path,
                'json_ld' => [$node],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function library(Request $request, array $filters): array
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $nonCanonical = $request->routeIs('library.search')
            || $search !== ''
            || collect($filters)
                ->except(['q', 'page'])
                ->contains(fn (mixed $value, string $key): bool => match ($key) {
                    'sort' => (string) $value !== 'newest',
                    'per_page' => (int) $value !== 12,
                    default => $value !== null && $value !== '',
                });

        $title = $search !== ''
            ? 'Hasil pencarian: '.$search
            : 'Katalog Ebook';

        $description = $search !== ''
            ? 'Hasil pencarian ebook untuk “'.$search.'”.'
            : 'Jelajahi katalog ebook yang tersedia untuk dibaca atau diunduh.';

        return $this->page(
            $request,
            $title,
            $description,
            [
                'canonical_path' => '/library',
                'keep_page' => ! $nonCanonical,
                'index' => ! $nonCanonical,
                'json_ld' => $nonCanonical ? [] : [[
                    '@context' => 'https://schema.org',
                    '@type' => 'CollectionPage',
                    '@id' => $this->canonical($request, '/library', true).'#catalog',
                    'url' => $this->canonical($request, '/library', true),
                    'name' => 'Katalog Ebook',
                    'description' => 'Jelajahi katalog ebook yang tersedia untuk dibaca atau diunduh.',
                ]],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function info(
        Request $request,
        string $kind,
        string $title,
        string $description,
    ): array {
        $path = $kind === 'contact' ? '/contact' : '/about';

        return $this->page(
            $request,
            $title,
            $description,
            [
                'canonical_path' => $path,
                'json_ld' => [[
                    '@context' => 'https://schema.org',
                    '@type' => $kind === 'contact' ? 'ContactPage' : 'AboutPage',
                    '@id' => $this->canonical($request, $path).'#page',
                    'url' => $this->canonical($request, $path),
                    'name' => $title,
                    'description' => $description,
                ]],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function notFound(Request $request): array
    {
        return $this->page(
            $request,
            'Halaman tidak ditemukan',
            'Halaman yang Anda cari tidak tersedia.',
            [
                'index' => false,
                'follow' => false,
                'canonical_path' => $request->getPathInfo(),
            ],
        );
    }

    public function canonical(
        Request $request,
        string $path,
        bool $keepPage = false,
    ): string {
        $path = '/'.ltrim($path, '/');
        $base = $this->baseUrl($request);
        $url = $path === '/' ? $base : $base.$path;

        if ($keepPage) {
            $page = filter_var($request->query('page'), FILTER_VALIDATE_INT);

            if ($page !== false && $page > 1) {
                $url .= '?page='.$page;
            }
        }

        return $url;
    }

    public function baseUrl(Request $request): string
    {
        $configured = trim((string) $this->settings->get('seo', 'canonical_url'));

        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return rtrim($request->getSchemeAndHttpHost(), '/');
    }

    public function absoluteUrl(Request $request, mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }

        return $this->baseUrl($request).'/'.ltrim($value, '/');
    }

    /**
     * @param  array<int, array{name: string, path: string}>  $items
     * @return array<string, mixed>
     */
    private function breadcrumb(Request $request, array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)
                ->values()
                ->map(fn (array $item, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $this->canonical($request, $item['path']),
                ])
                ->all(),
        ];
    }

    private function title(?string $title, string $suffix): string
    {
        $title = trim((string) $title);
        $suffix = trim($suffix);

        if ($title === '') {
            return $suffix !== '' ? $suffix : 'Digital Library';
        }

        if ($suffix === '' || Str::lower($title) === Str::lower($suffix)) {
            return $title;
        }

        return $title.' | '.$suffix;
    }

    private function description(mixed $value, string $fallback): string
    {
        $description = trim(is_scalar($value) ? (string) $value : '');

        if ($description === '') {
            $description = trim($fallback);
        }

        $description = preg_replace('/\s+/u', ' ', $description) ?: '';

        return Str::limit($description, 240, '');
    }
}
