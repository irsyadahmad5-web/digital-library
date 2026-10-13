<?php

namespace Tests\Feature;

use App\Modules\Analytics\Application\AnalyticsReport;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFooterUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
    }

    public function test_footer_statistics_are_privacy_safe_aggregates(): void
    {
        $statistics = app(AnalyticsReport::class)->footerStatistics();

        $this->assertSame(
            ['books', 'reads', 'categories', 'authors'],
            collect($statistics)->pluck('key')->all(),
        );
        $this->assertSame(
            ['Buku publik', 'Pembacaan', 'Kategori', 'Penulis'],
            collect($statistics)->pluck('label')->all(),
        );

        foreach ($statistics as $statistic) {
            $this->assertIsInt($statistic['value']);
            $this->assertGreaterThanOrEqual(0, $statistic['value']);
        }
    }

    public function test_public_layout_contains_compact_footer_contract(): void
    {
        $layout = (string) file_get_contents(
            resource_path('js/layouts/PublicLayout.vue'),
        );

        $this->assertStringNotContainsString('Ruang baca digital', $layout);
        $this->assertStringNotContainsString('Temukan bacaan yang layak dibawa lebih jauh.', $layout);
        $this->assertStringNotContainsString('Perpustakaan dalam angka', $layout);
        $this->assertStringContainsString('>Statistik</p>', $layout);
        $this->assertStringContainsString('Pengguna online', $layout);
        $this->assertStringContainsString('tel:', $layout);
        $this->assertStringContainsString('mailto:', $layout);
        $this->assertStringNotContainsString('Lihat informasi kontak', $layout);
        $this->assertStringContainsString('footerStats', $layout);
        $this->assertStringContainsString('Baca buku tanpa login', $layout);
        $this->assertStringContainsString('bg-[#151B28]', $layout);
    }
}
