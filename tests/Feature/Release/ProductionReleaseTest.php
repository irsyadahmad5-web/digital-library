<?php

namespace Tests\Feature\Release;

use App\Modules\Operations\Application\OperationsHealth;
use App\Modules\Operations\Application\RestoreReadiness;
use App\Modules\Quality\Application\ReleaseQualityGate;
use App\Modules\Release\Application\ProductionReleaseVerifier;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProductionReleaseTest extends TestCase
{
    public function test_version_file_and_release_config_follow_semver_and_channel(): void
    {
        $version = trim(
            (string) file_get_contents(base_path('VERSION')),
        );

        $this->assertSame($version, config('release.version'));
        $this->assertSame($this->expectedChannel($version), config('release.channel'));
        $this->assertMatchesRegularExpression(
            '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?$/',
            $version,
        );
    }

    public function test_release_info_command_supports_json_output(): void
    {
        $exit = Artisan::call('release:info', [
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);

        $payload = json_decode(Artisan::output(), true);

        $this->assertIsArray($payload);
        $version = trim((string) file_get_contents(base_path('VERSION')));

        $this->assertSame($version, $payload['version']);
        $this->assertSame($this->expectedChannel($version), $payload['channel']);
    }

    public function test_production_release_verifier_combines_quality_operations_and_restore_gates(): void
    {
        $verifier = new ProductionReleaseVerifier(
            $this->passingQuality(),
            $this->healthyOperations(),
            $this->readyRestore(),
        );

        $result = $verifier->verify();

        $this->assertTrue($result['passed']);
        $version = trim((string) file_get_contents(base_path('VERSION')));

        $this->assertSame($version, $result['version']);
        $this->assertSame($this->expectedChannel($version), $result['channel']);
        $this->assertNotNull($result['restore']);

        $keys = collect($result['checks'])
            ->pluck('key')
            ->all();

        $this->assertSame([
            'version',
            'channel',
            'quality',
            'operations',
            'restore',
        ], $keys);
    }

    public function test_restore_readiness_is_a_release_blocker_except_for_explicit_fresh_install(): void
    {
        $verifier = new ProductionReleaseVerifier(
            $this->passingQuality(),
            $this->healthyOperations(),
            $this->notReadyRestore(),
        );

        $normal = $verifier->verify();

        $this->assertFalse($normal['passed']);
        $this->assertFalse(
            collect($normal['checks'])
                ->firstWhere('key', 'restore')['passed'],
        );

        $fresh = $verifier->verify(true);

        $this->assertTrue($fresh['passed']);
        $this->assertNull($fresh['restore']);
        $this->assertTrue(
            collect($fresh['checks'])
                ->firstWhere('key', 'restore')['passed'],
        );
    }

    public function test_release_packaging_contract_excludes_secrets_and_includes_runtime_dependencies(): void
    {
        $script = (string) file_get_contents(
            base_path('tools/release-package.sh'),
        );

        $this->assertStringContainsString('git status --porcelain', $script);
        $this->assertStringContainsString('"$COMPOSER_BIN" qa', $script);
        $this->assertStringContainsString('"$COMPOSER_BIN" qa:browser', $script);
        $this->assertStringContainsString('git archive --format=tar HEAD', $script);
        $this->assertStringContainsString('--no-dev', $script);
        $this->assertStringContainsString('public/build/', $script);
        $this->assertStringContainsString('RELEASE.json', $script);
        $this->assertStringContainsString('CHANNEL="${RELEASE_CHANNEL:-}"', $script);
        $this->assertStringContainsString('"channel": "$CHANNEL"', $script);
        $this->assertStringContainsString('RELEASE_FILES.sha256', $script);
        $this->assertStringContainsString('digital-library-v$VERSION', $script);
        $this->assertStringContainsString('$STAGE/.env', $script);
        $this->assertStringContainsString(
            '$STAGE/storage/app/installer/bootstrap.key',
            $script,
        );
        $this->assertStringContainsString(
            'find "$STAGE/storage" -type f ! -name',
            $script,
        );

        $attributes = (string) file_get_contents(
            base_path('.gitattributes'),
        );

        $this->assertStringNotContainsString(
            'CHANGELOG.md export-ignore',
            $attributes,
        );

        $ignore = (string) file_get_contents(
            base_path('.gitignore'),
        );

        $this->assertStringContainsString('/dist', $ignore);
    }

    public function test_dynamic_robots_route_is_not_shadowed_by_static_file(): void
    {
        $this->assertFileDoesNotExist(public_path('robots.txt'));
        $this->assertNotNull(
            Route::getRoutes()->getByName('seo.robots'),
        );
    }

    public function test_apache_public_root_denies_dotfiles(): void
    {
        $htaccess = (string) file_get_contents(
            public_path('.htaccess'),
        );

        $this->assertStringContainsString(
            '<FilesMatch "^\\.">',
            $htaccess,
        );
        $this->assertStringContainsString(
            'Require all denied',
            $htaccess,
        );
    }

    public function test_release_changelog_and_runbooks_cover_candidate_and_stable_baseline(): void
    {
        $changelog = (string) file_get_contents(
            base_path('CHANGELOG.md'),
        );
        $runbook = (string) file_get_contents(
            base_path('docs/PRODUCTION_RELEASE.md'),
        );
        $candidate = (string) file_get_contents(
            base_path('docs/RELEASE_CANDIDATE_1.1.0.md'),
        );

        $this->assertStringContainsString(
            '## [1.1.0-rc.1] - 2026-10-11',
            $changelog,
        );
        $this->assertStringContainsString(
            '# Production Release 1.0.2',
            $runbook,
        );
        $this->assertStringContainsString(
            'php artisan release:verify',
            $runbook,
        );
        $this->assertStringContainsString(
            'Do not automatically run `migrate:rollback`',
            $runbook,
        );
        $this->assertStringContainsString(
            '# Release Candidate 1.1.0-rc.1',
            $candidate,
        );
        $this->assertStringContainsString(
            'Production tetap pada v1.0.2',
            $candidate,
        );
    }

    private function expectedChannel(string $version): string
    {
        return match (true) {
            str_contains($version, '-rc') => 'rc',
            str_contains($version, '-beta') => 'beta',
            str_contains($version, '-alpha') => 'alpha',
            default => 'stable',
        };
    }

    private function passingQuality(): ReleaseQualityGate
    {
        return new class extends ReleaseQualityGate
        {
            public function __construct() {}

            public function verify(bool $production = false): array
            {
                return [
                    'passed' => true,
                    'production' => $production,
                    'checks' => [],
                ];
            }
        };
    }

    private function healthyOperations(): OperationsHealth
    {
        return new class extends OperationsHealth
        {
            public function __construct() {}

            public function report(): array
            {
                return [
                    'status' => 'ok',
                    'checked_at' => now()->toIso8601String(),
                    'counts' => [
                        'ok' => 9,
                        'warning' => 0,
                        'critical' => 0,
                    ],
                    'checks' => [],
                ];
            }
        };
    }

    private function readyRestore(): RestoreReadiness
    {
        return new class extends RestoreReadiness
        {
            public function __construct() {}

            public function check(?string $backup = null): array
            {
                return [
                    'ready' => true,
                    'backup' => '20261006-100000-release',
                    'checks' => [],
                ];
            }
        };
    }

    private function notReadyRestore(): RestoreReadiness
    {
        return new class extends RestoreReadiness
        {
            public function __construct() {}

            public function check(?string $backup = null): array
            {
                return [
                    'ready' => false,
                    'backup' => null,
                    'checks' => [],
                ];
            }
        };
    }
}
