<?php

namespace Tests\Feature;

use App\Modules\Library\Domain\Models\Author;
use App\Modules\Library\Domain\Models\Category;
use App\Modules\Library\Domain\Models\Collection;
use App\Modules\Library\Domain\Models\Ebook;
use App\Modules\Library\Domain\Models\EbookFile;
use App\Modules\Library\Domain\Models\Publisher;
use App\Modules\Settings\Application\SettingsManager;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SeoSocialSharingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        Storage::fake('local');
        Storage::fake('public');

        app(SettingsManager::class)->updateGroup('seo', [
            'title_suffix' => 'Amal Baca',
            'meta_description' => 'Perpustakaan digital Amal Baca.',
            'og_title' => 'Amal Baca Digital Library',
            'og_description' => 'Baca koleksi digital Amal Baca.',
            'robots_index' => true,
            'canonical_url' => 'https://library.example.test',
            'google_site_verification' => 'verification-token-123',
        ], null);
    }

    public function test_home_renders_server_side_social_meta_canonical_and_structured_data(): void
    {
        app(SettingsManager::class)->updateGroup('general', [
            'site_name' => 'Perpustakaan Amal Baca',
            'short_name' => 'Amal Baca',
            'organization_name' => 'Yayasan Amal Baca',
            'description' => 'Perpustakaan untuk semua.',
            'tagline' => 'Baca untuk tumbuh.',
            'address' => '',
            'phone' => '',
            'email' => '',
        ], null);

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('<title data-inertia="">Amal Baca</title>', false)
            ->assertSee(
                '<link data-inertia="canonical" rel="canonical" href="https://library.example.test">',
                false,
            )
            ->assertSee('property="og:title"', false)
            ->assertSee('content="Amal Baca Digital Library"', false)
            ->assertSee('name="twitter:card"', false)
            ->assertSee('name="google-site-verification"', false)
            ->assertSee('verification-token-123', false)
            ->assertSee('type="application/ld+json"', false)
            ->assertSee('"@type":"WebSite"', false)
            ->assertSee('"@type":"Organization"', false)
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Public/Home')
                    ->where('seo.canonical', 'https://library.example.test')
                    ->where('seo.robots', 'index,follow')
                    ->where('seo.open_graph.title', 'Amal Baca Digital Library')
                    ->has('seo.json_ld', 2),
            );
    }

    public function test_book_uses_dynamic_metadata_cover_book_schema_and_breadcrumbs(): void
    {
        $book = $this->publicBook(
            'Belajar Laravel Modern',
            description: 'Panduan membangun aplikasi Laravel modern.',
            withRelations: true,
            cover: true,
        );

        $response = $this->get('/book/'.$book->slug);

        $response
            ->assertOk()
            ->assertSee('Belajar Laravel Modern | Amal Baca', false)
            ->assertSee(
                'href="https://library.example.test/book/'.$book->slug.'"',
                false,
            )
            ->assertSee('property="og:type" content="book"', false)
            ->assertSee('property="og:image"', false)
            ->assertSee('"@type":"Book"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('"isbn":"9786020000001"', false)
            ->assertSee('"numberOfPages":120', false)
            ->assertDontSee('ebooks/'.$book->getKey().'/private.pdf', false)
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Public/Book')
                    ->where('seo.open_graph.type', 'book')
                    ->where(
                        'seo.canonical',
                        'https://library.example.test/book/'.$book->slug,
                    )
                    ->where('seo.robots', 'index,follow')
                    ->has('seo.json_ld', 2),
            );
    }

    public function test_search_and_faceted_catalog_are_noindex_with_clean_catalog_canonical(): void
    {
        $this->publicBook('Laravel Searchable');

        $this->get('/search?q=Laravel')
            ->assertOk()
            ->assertSee(
                'name="robots" content="noindex,follow"',
                false,
            )
            ->assertSee(
                'rel="canonical" href="https://library.example.test/library"',
                false,
            )
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('seo.robots', 'noindex,follow')
                    ->where(
                        'seo.canonical',
                        'https://library.example.test/library',
                    ),
            );

        $this->get('/library?sort=title')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('seo.robots', 'noindex,follow')
                    ->where(
                        'seo.canonical',
                        'https://library.example.test/library',
                    ),
            );
    }

    public function test_clean_catalog_pagination_keeps_page_in_canonical(): void
    {
        $this->get('/library?page=2')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('seo.robots', 'index,follow')
                    ->where(
                        'seo.canonical',
                        'https://library.example.test/library?page=2',
                    ),
            );
    }

    public function test_taxonomy_sort_query_is_noindex_but_clean_taxonomy_is_indexable(): void
    {
        $category = Category::query()->create([
            'name' => 'Teknologi',
            'slug' => 'teknologi',
            'description' => 'Ebook teknologi.',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $this->publicBook('Teknologi Untuk Semua', category: $category);

        $this->get('/category/teknologi')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('seo.robots', 'index,follow')
                    ->where(
                        'seo.canonical',
                        'https://library.example.test/category/teknologi',
                    )
                    ->has('seo.json_ld', 2),
            );

        $this->get('/category/teknologi?sort=title')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('seo.robots', 'noindex,follow')
                    ->where(
                        'seo.canonical',
                        'https://library.example.test/category/teknologi',
                    ),
            );
    }

    public function test_sitemap_contains_only_public_indexable_library_pages(): void
    {
        $public = $this->publicBook('Sitemap Public', withRelations: true);
        $draft = $this->publicBook(
            'Sitemap Draft',
            publicationStatus: 'draft',
            publishedAt: null,
        );

        $response = $this->get('/sitemap.xml');

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(
                '<loc>https://library.example.test/book/'.$public->slug.'</loc>',
                false,
            )
            ->assertDontSee('/book/'.$draft->slug, false)
            ->assertSee(
                '<loc>https://library.example.test/category/teknologi</loc>',
                false,
            )
            ->assertSee(
                '<loc>https://library.example.test/author/ahmad-penulis</loc>',
                false,
            )
            ->assertSee(
                '<loc>https://library.example.test/publisher/penerbit-asyifa</loc>',
                false,
            )
            ->assertSee(
                '<loc>https://library.example.test/collection/koleksi-utama</loc>',
                false,
            )
            ->assertDontSee('/read/', false)
            ->assertDontSee('/download', false)
            ->assertDontSee('/search', false);
    }

    public function test_robots_respects_global_indexing_setting_and_advertises_sitemap(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Allow: /', false)
            ->assertSee('Disallow: /admin', false)
            ->assertSee('Disallow: /read/', false)
            ->assertSee(
                'Sitemap: https://library.example.test/sitemap.xml',
                false,
            );

        app(SettingsManager::class)->updateGroup('seo', [
            'robots_index' => false,
        ], null);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /', false)
            ->assertDontSee('Allow: /', false);

        $this->get('/')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('seo.robots', 'noindex,nofollow'),
            );
    }

    public function test_reader_source_and_download_send_x_robots_tag(): void
    {
        $book = $this->publicBook('Noindex PDF');
        $path = 'ebooks/'.$book->getKey().'/private.pdf';
        Storage::disk('local')->put($path, "%PDF-1.4\nseo\n%%EOF");

        $file = $book->file()->firstOrFail();
        $file->forceFill([
            'path' => $path,
            'size_bytes' => Storage::disk('local')->size($path),
            'sha256' => hash('sha256', Storage::disk('local')->get($path)),
        ])->save();

        $this->get('/read/'.$book->slug.'/file')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->streamedContent();

        $this->get('/book/'.$book->slug.'/download')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->streamedContent();
    }

    public function test_404_is_server_rendered_noindex_nofollow(): void
    {
        $this->get('/missing-seo-page')
            ->assertNotFound()
            ->assertSee(
                'name="robots" content="noindex,nofollow"',
                false,
            )
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Public/NotFound')
                    ->where('seo.robots', 'noindex,nofollow'),
            );
    }

    private function publicBook(
        string $title,
        ?string $description = null,
        ?Category $category = null,
        bool $withRelations = false,
        bool $cover = false,
        string $publicationStatus = 'published',
        mixed $publishedAt = 'now',
    ): Ebook {
        if ($withRelations) {
            $category ??= Category::query()->firstOrCreate(
                ['slug' => 'teknologi'],
                [
                    'name' => 'Teknologi',
                    'description' => 'Kategori teknologi.',
                    'is_active' => true,
                    'sort_order' => 0,
                ],
            );

            $author = Author::query()->firstOrCreate(
                ['slug' => 'ahmad-penulis'],
                [
                    'name' => 'Ahmad Penulis',
                    'bio' => 'Penulis ebook.',
                    'is_active' => true,
                ],
            );

            $publisher = Publisher::query()->firstOrCreate(
                ['slug' => 'penerbit-asyifa'],
                [
                    'name' => 'Penerbit Asyifa',
                    'description' => 'Penerbit digital.',
                    'is_active' => true,
                ],
            );

            $collection = Collection::query()->firstOrCreate(
                ['slug' => 'koleksi-utama'],
                [
                    'name' => 'Koleksi Utama',
                    'description' => 'Koleksi utama.',
                    'is_active' => true,
                ],
            );
        } else {
            $publisher = null;
            $collection = null;
        }

        $ebook = Ebook::query()->create([
            'title' => $title,
            'subtitle' => 'Subjudul '.$title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'isbn' => $withRelations ? '9786020000001' : null,
            'description' => $description,
            'publication_year' => 2026,
            'page_count' => 120,
            'cover_path' => $cover ? 'ebooks/covers/seo-cover.jpg' : null,
            'publisher_id' => $publisher?->getKey(),
            'collection_id' => $collection?->getKey(),
            'publication_status' => $publicationStatus,
            'read_enabled' => true,
            'download_enabled' => true,
            'published_at' => $publishedAt === 'now' ? now() : $publishedAt,
        ]);

        if ($category) {
            $ebook->categories()->attach($category);
        }

        if ($withRelations) {
            $ebook->authors()->attach($author, ['sort_order' => 1]);
        }

        if ($cover) {
            Storage::disk('public')->put(
                'ebooks/covers/seo-cover.jpg',
                'fake-jpeg-content',
            );
        }

        $pdf = "%PDF-1.4\nseo-stage-19\n%%EOF";
        $path = 'ebooks/'.$ebook->getKey().'/private.pdf';
        Storage::disk('local')->put($path, $pdf);

        EbookFile::query()->create([
            'ebook_id' => $ebook->getKey(),
            'source_type' => 'local',
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'private.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($pdf),
            'sha256' => hash('sha256', $pdf),
            'verification_status' => 'verified',
            'processing_status' => 'processed',
            'page_count' => 120,
            'processed_at' => now(),
        ]);

        return $ebook->fresh([
            'authors',
            'categories',
            'publisher',
            'collection',
            'language',
            'file',
        ]);
    }
}
