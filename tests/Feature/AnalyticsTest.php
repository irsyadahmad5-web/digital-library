<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Analytics\Application\AnalyticsReport;
use App\Modules\Analytics\Domain\Models\EbookDailyMetric;
use App\Modules\Analytics\Domain\Models\SiteDailyMetric;
use App\Modules\Identity\Domain\Models\Role;
use App\Modules\Library\Domain\Models\Category;
use App\Modules\Library\Domain\Models\Collection;
use App\Modules\Library\Domain\Models\Ebook;
use App\Modules\Library\Domain\Models\EbookDownloadStat;
use App\Modules\Library\Domain\Models\EbookFile;
use App\Modules\Settings\Application\SettingsManager;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            AccessControlSeeder::class,
            SettingsSeeder::class,
        ]);

        Storage::fake('local');
        app(SettingsManager::class)->flush();
    }

    public function test_public_activity_is_recorded_as_daily_aggregates_without_identity_data(): void
    {
        $fixture = $this->book('Analytics Book');

        $this->get('/')->assertOk();
        $this->get('/library?q='.urlencode('kata kunci tidak disimpan'))->assertOk();
        $this->get('/book/'.$fixture['ebook']->slug)->assertOk();
        $this->get('/read/'.$fixture['ebook']->slug)->assertOk();

        $this->get('/book/'.$fixture['ebook']->slug.'/download')
            ->assertOk()
            ->streamedContent();

        $date = now()->toDateString();

        $this->assertDatabaseHas('site_daily_metrics', [
            'metric_date' => $date,
            'page_views' => 4,
            'home_views' => 1,
            'catalog_views' => 1,
            'book_views' => 1,
            'reader_opens' => 1,
            'downloads' => 1,
        ]);

        $this->assertDatabaseHas('ebook_daily_metrics', [
            'metric_date' => $date,
            'ebook_id' => $fixture['ebook']->getKey(),
            'detail_views' => 1,
            'reader_opens' => 1,
            'downloads' => 1,
        ]);

        $this->assertDatabaseHas('ebook_download_stats', [
            'ebook_id' => $fixture['ebook']->getKey(),
            'downloads' => 1,
        ]);

        foreach ([
            ...Schema::getColumnListing('site_daily_metrics'),
            ...Schema::getColumnListing('ebook_daily_metrics'),
        ] as $column) {
            $this->assertNotContains($column, [
                'ip',
                'ip_address',
                'user_agent',
                'user_id',
                'query',
                'search_query',
            ]);
        }
    }

    public function test_prefetch_and_disabled_tracking_are_ignored(): void
    {
        $fixture = $this->book('Privacy Book');

        $this->get(
            '/book/'.$fixture['ebook']->slug,
            ['Purpose' => 'prefetch'],
        )->assertOk();

        $this->assertDatabaseCount('site_daily_metrics', 0);
        $this->assertDatabaseCount('ebook_daily_metrics', 0);

        app(SettingsManager::class)->updateGroup(
            'analytics',
            [
                'enabled' => false,
                'track_page_views' => true,
                'track_reader_opens' => true,
            ],
            null,
        );

        $this->get('/book/'.$fixture['ebook']->slug)->assertOk();
        $this->get('/read/'.$fixture['ebook']->slug)->assertOk();

        $this->assertDatabaseCount('site_daily_metrics', 0);
        $this->assertDatabaseCount('ebook_daily_metrics', 0);
    }

    public function test_page_and_reader_tracking_can_be_controlled_independently(): void
    {
        $fixture = $this->book('Configurable Analytics');

        app(SettingsManager::class)->updateGroup(
            'analytics',
            [
                'enabled' => true,
                'track_page_views' => true,
                'track_reader_opens' => false,
            ],
            null,
        );

        $this->get('/book/'.$fixture['ebook']->slug)->assertOk();
        $this->get('/read/'.$fixture['ebook']->slug)->assertOk();

        $this->assertDatabaseHas('site_daily_metrics', [
            'metric_date' => now()->toDateString(),
            'page_views' => 1,
            'book_views' => 1,
            'reader_opens' => 0,
        ]);

        $this->assertDatabaseHas('ebook_daily_metrics', [
            'ebook_id' => $fixture['ebook']->getKey(),
            'detail_views' => 1,
            'reader_opens' => 0,
        ]);
    }

    public function test_report_builds_zero_filled_trend_and_ranked_discovery_tables(): void
    {
        $categoryA = $this->category('Kategori A');
        $categoryB = $this->category('Kategori B');
        $collectionA = $this->collection('Koleksi A');
        $collectionB = $this->collection('Koleksi B');

        $bookA = $this->book(
            'Engagement Tinggi',
            category: $categoryA,
            collection: $collectionA,
        );
        $bookB = $this->book(
            'Page View Tinggi',
            category: $categoryB,
            collection: $collectionB,
        );

        $date = now()->subDay()->toDateString();

        SiteDailyMetric::query()->create([
            'metric_date' => $date,
            'page_views' => 15,
            'book_views' => 15,
            'reader_opens' => 3,
            'downloads' => 2,
        ]);

        EbookDailyMetric::query()->create([
            'metric_date' => $date,
            'ebook_id' => $bookA['ebook']->getKey(),
            'detail_views' => 5,
            'reader_opens' => 3,
            'downloads' => 2,
        ]);

        EbookDailyMetric::query()->create([
            'metric_date' => $date,
            'ebook_id' => $bookB['ebook']->getKey(),
            'detail_views' => 10,
            'reader_opens' => 0,
            'downloads' => 0,
        ]);

        $report = app(AnalyticsReport::class)->report(7);

        $this->assertSame(7, $report['range']);
        $this->assertCount(7, $report['trend']);
        $this->assertSame(15, $report['summary']['page_views']);
        $this->assertSame(
            $bookA['ebook']->getKey(),
            $report['top_books'][0]['id'],
        );
        $this->assertSame('Kategori A', $report['top_categories'][0]['name']);
        $this->assertSame('Koleksi A', $report['top_collections'][0]['name']);

        $today = collect($report['trend'])
            ->firstWhere('date', now()->toDateString());

        $this->assertSame(0, $today['page_views']);
        $this->assertSame(0, $today['downloads']);
    }

    public function test_admin_analytics_page_supports_range_and_exposes_tracking_state(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)
            ->get('/admin/analytics?range=7')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Admin/Analytics/Index')
                    ->where('analytics.range', 7)
                    ->has('analytics.trend', 7)
                    ->where('tracking.enabled', true)
                    ->where('tracking.track_page_views', true)
                    ->where('tracking.track_reader_opens', true)
                    ->where('tracking.track_downloads', true),
            );

        $this->actingAs($user)
            ->get('/admin/analytics?range=999')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('analytics.range', 30),
            );
    }

    public function test_dashboard_uses_real_collection_activity_download_and_storage_totals(): void
    {
        $fixture = $this->book('Dashboard Book', sizeBytes: 2048);

        EbookDailyMetric::query()->create([
            'metric_date' => now()->toDateString(),
            'ebook_id' => $fixture['ebook']->getKey(),
            'detail_views' => 3,
            'reader_opens' => 4,
            'downloads' => 2,
        ]);

        SiteDailyMetric::query()->create([
            'metric_date' => now()->toDateString(),
            'page_views' => 9,
            'book_views' => 3,
            'reader_opens' => 4,
            'downloads' => 2,
        ]);

        EbookDownloadStat::query()->create([
            'ebook_id' => $fixture['ebook']->getKey(),
            'downloads' => 9,
            'last_downloaded_at' => now(),
        ]);

        $this->actingAs($this->superAdmin())
            ->get('/admin')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Admin/Dashboard')
                    ->where('summary.total_ebooks', 1)
                    ->where('summary.public_ebooks', 1)
                    ->where('summary.reader_opens', 4)
                    ->where('summary.downloads', 9)
                    ->where('summary.local_storage_bytes', 2048)
                    ->where('summary.last_30_days.page_views', 9),
            );
    }

    public function test_public_statistics_are_aggregate_library_totals_only(): void
    {
        $category = $this->category('Statistik');
        $fixture = $this->book(
            'Public Statistics',
            category: $category,
        );

        EbookDownloadStat::query()->create([
            'ebook_id' => $fixture['ebook']->getKey(),
            'downloads' => 12,
            'last_downloaded_at' => now(),
        ]);

        $stats = collect(app(AnalyticsReport::class)->publicStatistics())
            ->keyBy('key');

        $this->assertSame(1, $stats['ebooks']['value']);
        $this->assertSame(1, $stats['categories']['value']);
        $this->assertSame(12, $stats['downloads']['value']);
        $this->assertArrayNotHasKey('visitors', $stats->all());
        $this->assertArrayNotHasKey('searches', $stats->all());
    }

    /**
     * @return array{ebook: Ebook, file: EbookFile}
     */
    private function book(
        string $title,
        ?Category $category = null,
        ?Collection $collection = null,
        int $sizeBytes = 128,
    ): array {
        $category ??= $this->category('Category '.Str::random(8));
        $collection ??= $this->collection('Collection '.Str::random(8));

        $ebook = Ebook::query()->create([
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(8)),
            'collection_id' => $collection->getKey(),
            'publication_status' => 'published',
            'read_enabled' => true,
            'download_enabled' => true,
            'published_at' => now(),
            'page_count' => 2,
        ]);

        $ebook->categories()->attach($category);

        $pdf = "%PDF-1.4\nanalytics\n%%EOF";
        $path = 'ebooks/'.$ebook->getKey().'/analytics.pdf';
        Storage::disk('local')->put($path, $pdf);

        $file = EbookFile::query()->create([
            'ebook_id' => $ebook->getKey(),
            'source_type' => 'local',
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'analytics.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => $sizeBytes,
            'sha256' => hash('sha256', $pdf),
            'verification_status' => 'verified',
            'processing_status' => 'processed',
            'page_count' => 2,
            'processed_at' => now(),
        ]);

        return compact('ebook', 'file');
    }

    private function category(string $name): Category
    {
        return Category::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => 'Deskripsi '.$name,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function collection(string $name): Collection
    {
        return Collection::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => 'Deskripsi '.$name,
            'is_active' => true,
        ]);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::query()
            ->where('slug', 'super-admin')
            ->firstOrFail();

        $user->roles()->attach($role);

        return $user;
    }
}
