<?php

namespace Tests\Feature;

use App\Modules\Settings\Application\SettingsManager;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaMobilePolishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
    }

    public function test_manifest_uses_current_site_identity_and_installable_icons(): void
    {
        app(SettingsManager::class)->updateGroup('general', [
            'site_name' => 'Perpustakaan Amal Baca',
            'short_name' => 'Amal Baca',
            'description' => 'Baca ebook secara nyaman.',
        ], null);

        app(SettingsManager::class)->updateGroup('appearance', [
            'primary_color' => '#123456',
            'background_color' => '#F5F5F0',
        ], null);

        $response = $this->get('/manifest.webmanifest');

        $response
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                'application/manifest+json; charset=UTF-8',
            )
            ->assertHeader(
                'X-Robots-Tag',
                'noindex, nofollow, noarchive',
            )
            ->assertJsonPath('id', '/')
            ->assertJsonPath('name', 'Perpustakaan Amal Baca')
            ->assertJsonPath('short_name', 'Amal Baca')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('start_url', '/')
            ->assertJsonPath('scope', '/')
            ->assertJsonPath('theme_color', '#123456')
            ->assertJsonPath('background_color', '#F5F5F0')
            ->assertJsonPath('icons.0.sizes', '192x192')
            ->assertJsonPath('icons.1.sizes', '512x512')
            ->assertJsonPath('icons.2.purpose', 'maskable')
            ->assertJsonPath('shortcuts.0.url', '/library')
            ->assertJsonPath('shortcuts.1.url', '/search');
    }

    public function test_manifest_remains_available_during_public_maintenance(): void
    {
        app(SettingsManager::class)->updateGroup('maintenance', [
            'enabled' => true,
            'message' => 'Maintenance PWA.',
        ], null);

        $this->get('/')
            ->assertStatus(503);

        $this->get('/manifest.webmanifest')
            ->assertOk()
            ->assertJsonPath('start_url', '/');
    }

    public function test_application_shell_exposes_pwa_and_safe_area_metadata(): void
    {
        app(SettingsManager::class)->updateGroup('appearance', [
            'primary_color' => '#345678',
        ], null);

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee(
                'width=device-width, initial-scale=1, viewport-fit=cover',
                false,
            )
            ->assertSee(
                'name="theme-color" content="#345678"',
                false,
            )
            ->assertSee('rel="manifest"', false)
            ->assertSee('/manifest.webmanifest', false)
            ->assertSee(
                'rel="apple-touch-icon" sizes="180x180" href="/pwa/apple-touch-icon.png"',
                false,
            )
            ->assertSee(
                'name="apple-mobile-web-app-capable" content="yes"',
                false,
            );
    }

    public function test_pwa_icons_are_real_png_assets_with_expected_dimensions(): void
    {
        foreach ([
            'apple-touch-icon.png' => [180, 180],
            'icon-192.png' => [192, 192],
            'icon-512.png' => [512, 512],
            'icon-maskable-512.png' => [512, 512],
        ] as $name => [$expectedWidth, $expectedHeight]) {
            $path = public_path('pwa/'.$name);

            $this->assertFileExists($path);

            $size = getimagesize($path);

            $this->assertIsArray($size);
            $this->assertSame($expectedWidth, $size[0]);
            $this->assertSame($expectedHeight, $size[1]);
            $this->assertSame('image/png', $size['mime']);
        }
    }

    public function test_service_worker_has_safe_offline_and_update_strategy(): void
    {
        $worker = (string) file_get_contents(
            public_path('sw.js'),
        );

        $this->assertStringContainsString(
            "pathname.startsWith('/admin')",
            $worker,
        );
        $this->assertStringContainsString(
            "pathname.startsWith('/install')",
            $worker,
        );
        $this->assertStringContainsString(
            "pathname.startsWith('/read/')",
            $worker,
        );
        $this->assertStringContainsString(
            '/\\/book\\/[^/]+\\/download$/',
            $worker,
        );
        $this->assertStringContainsString(
            "cache.match('/offline.html')",
            $worker,
        );
        $this->assertStringContainsString(
            "url.pathname.startsWith('/build/')",
            $worker,
        );
        $this->assertStringContainsString(
            "event.data?.type === 'SKIP_WAITING'",
            $worker,
        );
        $this->assertStringContainsString(
            "key.startsWith('digital-library-')",
            $worker,
        );
        $this->assertStringNotContainsString(
            'caches.match(request).then',
            $worker,
        );
    }

    public function test_offline_fallback_is_self_contained_and_csp_friendly(): void
    {
        $offline = (string) file_get_contents(
            public_path('offline.html'),
        );

        $this->assertStringContainsString(
            'Koneksi internet tidak tersedia.',
            $offline,
        );
        $this->assertStringContainsString(
            'viewport-fit=cover',
            $offline,
        );
        $this->assertStringContainsString(
            '<form action="/" method="get">',
            $offline,
        );
        $this->assertStringNotContainsString(
            'onclick=',
            strtolower($offline),
        );
        $this->assertStringNotContainsString(
            'http://',
            strtolower($offline),
        );
        $this->assertStringNotContainsString(
            'https://',
            strtolower($offline),
        );
    }

    public function test_frontend_registers_pwa_and_surfaces_install_update_and_offline_state(): void
    {
        $app = (string) file_get_contents(
            resource_path('js/app.ts'),
        );
        $pwa = (string) file_get_contents(
            resource_path('js/composables/pwa.ts'),
        );
        $status = (string) file_get_contents(
            resource_path('js/components/public/PwaStatus.vue'),
        );

        $this->assertStringContainsString(
            'initializePwa',
            $app,
        );
        $this->assertStringContainsString(
            "navigator.serviceWorker.register('/sw.js'",
            $pwa,
        );
        $this->assertStringContainsString(
            'beforeinstallprompt',
            $pwa,
        );
        $this->assertStringContainsString(
            'appinstalled',
            $pwa,
        );
        $this->assertStringContainsString(
            'controllerchange',
            $pwa,
        );
        $this->assertStringContainsString(
            'Anda sedang offline',
            $status,
        );
        $this->assertStringContainsString(
            'Versi baru tersedia',
            $status,
        );
        $this->assertStringContainsString(
            'Tambahkan ke Layar Utama',
            $status,
        );
    }

    public function test_public_and_reader_layouts_include_mobile_safe_area_and_touch_polish(): void
    {
        $publicLayout = (string) file_get_contents(
            resource_path('js/layouts/PublicLayout.vue'),
        );
        $sheet = (string) file_get_contents(
            resource_path('js/components/ui/sheet/SheetShell.vue'),
        );
        $readerLayout = (string) file_get_contents(
            resource_path('js/layouts/ReaderLayout.vue'),
        );
        $reader = (string) file_get_contents(
            resource_path('js/pages/Reader/Index.vue'),
        );
        $drawer = (string) file_get_contents(
            resource_path('js/components/reader/ReaderDrawer.vue'),
        );
        $css = (string) file_get_contents(
            resource_path('css/app.css'),
        );

        $this->assertStringContainsString(
            'safe-x min-h-screen',
            $publicLayout,
        );
        $this->assertStringContainsString(
            'safe-top sticky',
            $publicLayout,
        );
        $this->assertStringContainsString(
            '<SheetShell',
            $publicLayout,
        );
        $this->assertStringContainsString(
            'max-h-[88dvh]',
            $sheet,
        );
        $this->assertStringContainsString(
            'safe-area-inset-bottom',
            $sheet,
        );
        $this->assertStringContainsString(
            'reader-controls-safe',
            $readerLayout,
        );
        $this->assertStringContainsString(
            'reader-scroller',
            $reader,
        );
        $this->assertStringContainsString(
            'size-11',
            $reader,
        );
        $this->assertStringContainsString(
            'reader-drawer-safe',
            $drawer,
        );
        $this->assertStringContainsString(
            'safe-area-inset-top',
            $css,
        );
        $this->assertStringContainsString(
            '@media (pointer: coarse)',
            $css,
        );
    }
}
