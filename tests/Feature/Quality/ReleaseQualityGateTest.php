<?php

namespace Tests\Feature\Quality;

use App\Modules\Quality\Application\ReleaseQualityGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ReleaseQualityGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_release_quality_gate_passes_for_complete_test_build(): void
    {
        $result = app(ReleaseQualityGate::class)->verify();

        $this->assertTrue(
            $result['passed'],
            json_encode(
                $result,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ),
        );

        $keys = collect($result['checks'])->pluck('key')->all();

        foreach ([
            'php',
            'composer_autoload',
            'database',
            'public_artifacts',
            'vite_manifest',
            'pwa_icons',
            'writable_paths',
            'pdf_toolchain',
            'database_tools',
        ] as $key) {
            $this->assertContains($key, $keys);
        }
    }

    public function test_quality_command_supports_machine_readable_output(): void
    {
        $exit = Artisan::call('quality:verify', [
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);

        $decoded = json_decode(Artisan::output(), true);

        $this->assertIsArray($decoded);
        $this->assertTrue($decoded['passed']);
        $this->assertFalse($decoded['production']);
        $this->assertNotEmpty($decoded['checks']);
    }

    public function test_missing_release_artifact_fails_closed(): void
    {
        config([
            'quality.required_public_files' => [
                'build/manifest.json',
                'missing-stage-25-artifact.txt',
            ],
        ]);

        $result = app(ReleaseQualityGate::class)->verify();
        $artifact = collect($result['checks'])
            ->firstWhere('key', 'public_artifacts');

        $this->assertFalse($result['passed']);
        $this->assertIsArray($artifact);
        $this->assertFalse($artifact['passed']);
        $this->assertStringContainsString(
            'missing-stage-25-artifact.txt',
            $artifact['message'],
        );
    }

    public function test_production_gate_rejects_non_production_runtime(): void
    {
        $result = app(ReleaseQualityGate::class)->verify(true);
        $environment = collect($result['checks'])
            ->firstWhere('key', 'production_environment');

        $this->assertFalse($result['passed']);
        $this->assertTrue($result['production']);
        $this->assertIsArray($environment);
        $this->assertFalse($environment['passed']);

        $keys = collect($result['checks'])->pluck('key')->all();

        foreach ([
            'production_debug',
            'production_https',
            'production_app_key',
            'production_installed',
            'production_host_guard',
            'production_csp',
            'production_hsts',
            'production_migrations',
            'production_storage_link',
        ] as $key) {
            $this->assertContains($key, $keys);
        }
    }

    public function test_invalid_pwa_icon_contract_fails_release_gate(): void
    {
        config([
            'quality.pwa_icons' => [
                'pwa/icon-192.png' => [512, 512],
            ],
        ]);

        $result = app(ReleaseQualityGate::class)->verify();
        $icons = collect($result['checks'])
            ->firstWhere('key', 'pwa_icons');

        $this->assertFalse($result['passed']);
        $this->assertIsArray($icons);
        $this->assertFalse($icons['passed']);
        $this->assertStringContainsString(
            'pwa/icon-192.png',
            $icons['message'],
        );
    }
}
