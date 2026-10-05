<?php

namespace Tests\Feature;

use App\Modules\Library\Application\PublicLibrary\PublicLibraryCache;
use App\Modules\Library\Application\PublicLibrary\PublicLibraryCatalog;
use App\Modules\Library\Domain\Models\Author;
use App\Modules\Library\Domain\Models\Category;
use App\Modules\Library\Domain\Models\Collection;
use App\Modules\Library\Domain\Models\Ebook;
use App\Modules\Library\Domain\Models\EbookFile;
use App\Modules\Library\Domain\Models\Language;
use App\Modules\Library\Domain\Models\Publisher;
use App\Modules\Seo\Application\SitemapBuilder;
use App\Modules\Settings\Application\HomepageBuilderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PerformanceOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_public_catalog_query_count_stays_constant_for_large_page(): void
    {
        $this->seedPublicBooks(120);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $page = app(PublicLibraryCatalog::class)->paginate([], 48);
        $queryCount = count(DB::getQueryLog());

        $this->assertSame(120, $page->total());
        $this->assertCount(48, $page->items());
        $this->assertLessThanOrEqual(
            8,
            $queryCount,
            "Expected the 48-book catalog page to stay within 8 SQL queries, got {$queryCount}.",
        );

        $sql = strtolower(collect(DB::getQueryLog())
            ->pluck('query')
            ->implode("\n"));

        $this->assertStringNotContainsString('from "tags"', $sql);
        $this->assertStringNotContainsString('from "ebook_download_stats"', $sql);
    }

    public function test_public_directory_cache_eliminates_repeat_queries_and_invalidates_on_content_change(): void
    {
        $fixture = $this->seedPublicBooks(1);
        $catalog = app(PublicLibraryCatalog::class);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $first = $catalog->filterOptions();
        $firstQueries = count(DB::getQueryLog());

        DB::flushQueryLog();
        $second = $catalog->filterOptions();
        $secondQueries = count(DB::getQueryLog());

        $this->assertGreaterThan(0, $firstQueries);
        $this->assertSame(0, $secondQueries);
        $this->assertSame(
            $first['categories'][0]['name'],
            $second['categories'][0]['name'],
        );

        $fixture['category']->forceFill(['name' => 'Kategori Cepat'])->save();

        DB::flushQueryLog();
        $afterMutation = $catalog->filterOptions();
        $afterMutationQueries = count(DB::getQueryLog());

        $this->assertGreaterThan(0, $afterMutationQueries);
        $this->assertSame(
            'Kategori Cepat',
            $afterMutation['categories'][0]['name'],
        );
    }

    public function test_homepage_and_sitemap_payloads_are_reused_from_application_cache(): void
    {
        $this->seedPublicBooks(3);

        $homepage = app(HomepageBuilderManager::class);
        $homepage->ensureDefaults();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $firstHomepage = $homepage->publicPayload();
        $firstHomepageQueries = count(DB::getQueryLog());

        DB::flushQueryLog();
        $secondHomepage = $homepage->publicPayload();
        $secondHomepageQueries = count(DB::getQueryLog());

        $this->assertNotEmpty($firstHomepage);
        $this->assertSame($firstHomepage, $secondHomepage);
        $this->assertGreaterThan(0, $firstHomepageQueries);
        $this->assertSame(0, $secondHomepageQueries);

        $request = request()->create('https://library.example.test/sitemap.xml');
        $sitemap = app(SitemapBuilder::class);

        DB::flushQueryLog();
        $firstEntries = $sitemap->entries($request);
        $firstSitemapQueries = count(DB::getQueryLog());

        DB::flushQueryLog();
        $secondEntries = $sitemap->entries($request);
        $secondSitemapQueries = count(DB::getQueryLog());

        $this->assertSame($firstEntries, $secondEntries);
        $this->assertGreaterThan(0, $firstSitemapQueries);
        $this->assertSame(0, $secondSitemapQueries);
    }

    public function test_stage_20_performance_indexes_are_present(): void
    {
        $ebookIndexes = $this->sqliteIndexNames('ebooks');
        $fileIndexes = $this->sqliteIndexNames('ebook_files');
        $uploadIndexes = $this->sqliteIndexNames('ebook_upload_sessions');

        $this->assertContains('idx_ebooks_public_feed', $ebookIndexes);
        $this->assertContains('idx_ebooks_status_year_title', $ebookIndexes);
        $this->assertContains('idx_ebooks_updated_id', $ebookIndexes);
        $this->assertContains('idx_ebook_files_public_ready', $fileIndexes);
        $this->assertContains('idx_ebook_files_external_due', $fileIndexes);
        $this->assertContains(
            'idx_ebook_upload_sessions_status_expiry',
            $uploadIndexes,
        );
    }

    public function test_local_pdf_revalidation_returns_304_and_if_range_protects_resumes(): void
    {
        $fixture = $this->seedPublicBooks(1);
        $ebook = $fixture['ebooks'][0];
        $file = $ebook->file()->firstOrFail();

        $pdf = "%PDF-1.4\nperformance-stage-20\n%%EOF";
        Storage::disk('local')->put((string) $file->path, $pdf);

        $file->forceFill([
            'size_bytes' => strlen($pdf),
            'sha256' => hash('sha256', $pdf),
        ])->save();

        $initial = $this->get('/read/'.$ebook->slug.'/file');

        $initial
            ->assertOk()
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $etag = (string) $initial->headers->get('ETag');
        $lastModified = (string) $initial->headers->get('Last-Modified');

        $this->assertNotSame('', $etag);
        $this->assertNotSame('', $lastModified);

        $this->withHeaders(['If-None-Match' => $etag])
            ->get('/read/'.$ebook->slug.'/file')
            ->assertStatus(304)
            ->assertHeader('ETag', $etag);

        $this->withHeaders([
            'If-None-Match' => '',
            'Range' => 'bytes=0-9',
            'If-Range' => '"stale-etag"',
        ])
            ->get('/read/'.$ebook->slug.'/file')
            ->assertOk()
            ->assertHeaderMissing('Content-Range');

        $this->withHeaders([
            'If-None-Match' => '',
            'Range' => 'bytes=0-9',
            'If-Range' => $etag,
        ])
            ->get('/read/'.$ebook->slug.'/file')
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-9/'.strlen($pdf));
    }

    public function test_large_local_pdf_serves_small_ranges_without_full_transfer(): void
    {
        $fixture = $this->seedPublicBooks(1);
        $ebook = $fixture['ebooks'][0];
        $file = $ebook->file()->firstOrFail();

        $pdf = "%PDF-1.4\n".str_repeat('A', 8 * 1024 * 1024)."\n%%EOF";
        Storage::disk('local')->put((string) $file->path, $pdf);

        $file->forceFill([
            'size_bytes' => strlen($pdf),
            'sha256' => hash('sha256', $pdf),
        ])->save();

        $start = 1024 * 1024;
        $end = $start + 1023;

        $response = $this->withHeaders([
            'Range' => "bytes={$start}-{$end}",
        ])->get('/read/'.$ebook->slug.'/file');

        $response
            ->assertStatus(206)
            ->assertHeader('Content-Length', '1024')
            ->assertHeader(
                'Content-Range',
                "bytes {$start}-{$end}/".strlen($pdf),
            );

        $this->assertSame(1024, strlen($response->streamedContent()));
    }

    public function test_sitemap_and_robots_support_conditional_http_revalidation(): void
    {
        $this->seedPublicBooks(1);

        $sitemap = $this->get('/sitemap.xml');
        $sitemap->assertOk();

        $cacheControl = (string) $sitemap->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);
        $this->assertStringContainsString(
            'stale-while-revalidate=300',
            $cacheControl,
        );

        $sitemapEtag = (string) $sitemap->headers->get('ETag');
        $this->assertNotSame('', $sitemapEtag);

        $this->withHeaders(['If-None-Match' => $sitemapEtag])
            ->get('/sitemap.xml')
            ->assertStatus(304)
            ->assertHeader('ETag', $sitemapEtag);

        $this->withHeaders(['If-None-Match' => ''])
            ->get('/robots.txt')
            ->assertOk()
            ->assertHeader('ETag');
    }

    public function test_cache_generation_changes_without_global_cache_flush(): void
    {
        $cache = app(PublicLibraryCache::class);
        $before = $cache->version();

        $cache->remember('probe', 300, fn (): string => 'cached-value');
        $oldKey = $cache->key('probe');

        $cache->flush();

        $after = $cache->version();
        $newKey = $cache->key('probe');

        $this->assertGreaterThan($before, $after);
        $this->assertNotSame($oldKey, $newKey);
    }

    /**
     * @return array{
     *     ebooks: array<int, Ebook>,
     *     author: Author,
     *     category: Category,
     *     publisher: Publisher,
     *     collection: Collection,
     *     language: Language
     * }
     */
    private function seedPublicBooks(int $count): array
    {
        $author = Author::query()->create([
            'name' => 'Penulis Performa',
            'slug' => 'penulis-performa',
            'bio' => 'Penulis untuk pengujian performa.',
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Kategori Performa',
            'slug' => 'kategori-performa',
            'description' => 'Kategori untuk pengujian performa.',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $publisher = Publisher::query()->create([
            'name' => 'Penerbit Performa',
            'slug' => 'penerbit-performa',
            'description' => 'Penerbit untuk pengujian performa.',
            'is_active' => true,
        ]);
        $collection = Collection::query()->create([
            'name' => 'Koleksi Performa',
            'slug' => 'koleksi-performa',
            'description' => 'Koleksi untuk pengujian performa.',
            'is_active' => true,
        ]);
        $language = Language::query()->create([
            'code' => 'id-perf',
            'name' => 'Indonesia Performa',
            'native_name' => 'Indonesia Performa',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $ebooks = [];

        for ($index = 1; $index <= $count; $index++) {
            $ebook = Ebook::query()->create([
                'title' => sprintf('Ebook Performa %03d', $index),
                'slug' => sprintf('ebook-performa-%03d-%s', $index, Str::lower(Str::random(4))),
                'description' => 'Deskripsi singkat untuk pengujian performa.',
                'publication_year' => 2026,
                'page_count' => 100 + $index,
                'publisher_id' => $publisher->getKey(),
                'language_id' => $language->getKey(),
                'collection_id' => $collection->getKey(),
                'publication_status' => 'published',
                'read_enabled' => true,
                'download_enabled' => true,
                'published_at' => now()->subSeconds($index),
            ]);

            $ebook->authors()->attach($author, ['sort_order' => 0]);
            $ebook->categories()->attach($category);

            $path = 'ebooks/'.$ebook->getKey().'/book.pdf';

            EbookFile::query()->create([
                'ebook_id' => $ebook->getKey(),
                'source_type' => 'local',
                'disk' => 'local',
                'path' => $path,
                'original_name' => 'book.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 1024,
                'sha256' => hash('sha256', $ebook->slug),
                'verification_status' => 'verified',
                'processing_status' => 'processed',
                'page_count' => 100 + $index,
                'preview_path' => null,
                'processed_at' => now(),
            ]);

            $ebooks[] = $ebook;
        }

        return compact(
            'ebooks',
            'author',
            'category',
            'publisher',
            'collection',
            'language',
        );
    }

    /**
     * @return array<int, string>
     */
    private function sqliteIndexNames(string $table): array
    {
        return collect(DB::select("PRAGMA index_list('{$table}')"))
            ->pluck('name')
            ->map(fn (mixed $name): string => (string) $name)
            ->values()
            ->all();
    }
}
