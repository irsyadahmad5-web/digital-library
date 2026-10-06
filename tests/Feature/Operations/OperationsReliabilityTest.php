<?php

namespace Tests\Feature\Operations;

use App\Modules\Operations\Application\BackupManager;
use App\Modules\Operations\Application\DatabaseBackup;
use App\Modules\Operations\Application\OperationsHealth;
use App\Modules\Operations\Application\RestoreReadiness;
use App\Modules\Operations\Application\SchedulerHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OperationsReliabilityTest extends TestCase
{
    use RefreshDatabase;

    private string $sandbox;

    /**
     * @var array<string, mixed>
     */
    private array $originalConfig = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = storage_path(
            'framework/testing-operations-'.bin2hex(random_bytes(6)),
        );

        File::ensureDirectoryExists(
            $this->sandbox.'/private/ebooks',
        );
        File::ensureDirectoryExists(
            $this->sandbox.'/public/ebooks',
        );

        foreach ([
            'operations.backup.enabled',
            'operations.backup.path',
            'operations.backup.retention_days',
            'operations.backup.retention_count',
            'operations.backup.max_age_hours',
            'operations.backup.sources',
            'operations.health.heartbeat_path',
            'operations.health.snapshot_path',
            'operations.health.scheduler_max_age_seconds',
        ] as $key) {
            $this->originalConfig[$key] = config($key);
        }

        config([
            'operations.backup.enabled' => true,
            'operations.backup.path' => $this->sandbox.'/backups',
            'operations.backup.retention_days' => 30,
            'operations.backup.retention_count' => 14,
            'operations.backup.max_age_hours' => 30,
            'operations.backup.sources' => [
                'private/ebooks' => $this->sandbox.'/private/ebooks',
                'public/ebooks' => $this->sandbox.'/public/ebooks',
            ],
            'operations.health.heartbeat_path' => $this->sandbox.'/heartbeat.json',
            'operations.health.snapshot_path' => $this->sandbox.'/health.json',
            'operations.health.scheduler_max_age_seconds' => 180,
        ]);

        file_put_contents(
            $this->sandbox.'/private/ebooks/book.pdf',
            '%PDF-stage-23-private%',
        );
        file_put_contents(
            $this->sandbox.'/public/ebooks/cover.webp',
            'stage-23-cover',
        );

        app()->instance(
            DatabaseBackup::class,
            new class extends DatabaseBackup
            {
                public function dump(string $directory): array
                {
                    file_put_contents(
                        $directory.'/database.sqlite',
                        'sqlite-backup-stage-23',
                    );

                    return [
                        'driver' => 'sqlite',
                        'file' => 'database.sqlite',
                        'binary' => null,
                    ];
                }
            },
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        config($this->originalConfig);
        File::deleteDirectory($this->sandbox);

        parent::tearDown();
    }

    public function test_backup_snapshot_is_atomic_verifiable_and_contains_required_assets(): void
    {
        $manager = app(BackupManager::class);
        $backup = $manager->create();

        $this->assertMatchesRegularExpression(
            '/^\d{8}-\d{6}-[a-f0-9]{8}$/',
            $backup['name'],
        );
        $this->assertDirectoryExists($backup['path']);
        $this->assertFileExists(
            $backup['path'].'/database.sqlite',
        );
        $this->assertFileExists(
            $backup['path'].'/private/ebooks/book.pdf',
        );
        $this->assertFileExists(
            $backup['path'].'/public/ebooks/cover.webp',
        );
        $this->assertFileExists(
            $backup['path'].'/manifest.json',
        );

        $verification = $manager->verify($backup['name']);

        $this->assertTrue($verification['valid']);
        $this->assertSame(3, $verification['checked_files']);

        $manifest = (string) file_get_contents(
            $backup['path'].'/manifest.json',
        );

        $this->assertStringNotContainsString(
            'DB_PASSWORD',
            $manifest,
        );
        $this->assertStringNotContainsString(
            'APP_KEY',
            $manifest,
        );
    }

    public function test_backup_verification_detects_tampering_and_rejects_path_traversal(): void
    {
        $manager = app(BackupManager::class);
        $backup = $manager->create();

        file_put_contents(
            $backup['path'].'/private/ebooks/book.pdf',
            'tampered',
        );

        $verification = $manager->verify($backup['name']);

        $this->assertFalse($verification['valid']);
        $this->assertNotEmpty($verification['errors']);

        $this->expectException(\RuntimeException::class);

        $manager->verify('../outside');
    }

    public function test_backup_verification_rejects_unexpected_files_not_in_manifest(): void
    {
        $manager = app(BackupManager::class);
        $backup = $manager->create();

        file_put_contents(
            $backup['path'].'/unexpected.txt',
            'unexpected',
        );

        $verification = $manager->verify($backup['name']);

        $this->assertFalse($verification['valid']);
        $this->assertTrue(
            collect($verification['errors'])
                ->contains(
                    fn (string $error): bool => str_contains(
                        $error,
                        'File tidak terdaftar di manifest',
                    ),
                ),
        );
    }

    public function test_backup_path_can_never_be_inside_public_web_root(): void
    {
        config([
            'operations.backup.path' => public_path(
                'unsafe-backups',
            ),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Path backup tidak boleh berada di dalam public web root.',
        );

        app(BackupManager::class)->create();
    }

    public function test_retention_prunes_backups_beyond_configured_count(): void
    {
        config([
            'operations.backup.retention_count' => 2,
            'operations.backup.retention_days' => 365,
        ]);

        $manager = app(BackupManager::class);

        Carbon::setTestNow('2026-10-06 01:00:00');
        $first = $manager->create();

        Carbon::setTestNow('2026-10-06 01:01:00');
        $second = $manager->create();

        Carbon::setTestNow('2026-10-06 01:02:00');
        $third = $manager->create();

        $result = $manager->prune();

        $this->assertContains(
            $first['name'],
            $result['deleted'],
        );
        $this->assertContains(
            $second['name'],
            $result['kept'],
        );
        $this->assertContains(
            $third['name'],
            $result['kept'],
        );
        $this->assertDirectoryDoesNotExist(
            $first['path'],
        );
    }

    public function test_restore_readiness_requires_valid_backup_and_writable_storage(): void
    {
        $manager = app(BackupManager::class);
        $backup = $manager->create();

        $readiness = app(RestoreReadiness::class)
            ->check($backup['name']);

        $this->assertTrue($readiness['ready']);
        $this->assertSame(
            $backup['name'],
            $readiness['backup'],
        );
        $this->assertTrue(
            collect($readiness['checks'])
                ->every(
                    fn (array $check): bool => $check['ok'],
                ),
        );
    }

    public function test_health_report_tracks_scheduler_database_disk_storage_and_backup_state(): void
    {
        app(SchedulerHeartbeat::class)->touch();
        app(BackupManager::class)->create();

        $report = app(OperationsHealth::class)->report();

        $this->assertSame(
            'ok',
            $report['status'],
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
        $this->assertSame(0, $report['counts']['critical']);
        $this->assertSame(0, $report['counts']['warning']);

        $keys = collect($report['checks'])
            ->pluck('key')
            ->all();

        foreach ([
            'installation',
            'database',
            'migrations',
            'storage',
            'disk',
            'scheduler',
            'backup',
            'failed_jobs',
            'production_debug',
        ] as $key) {
            $this->assertContains($key, $keys);
        }

        app(OperationsHealth::class)
            ->writeSnapshot($report);

        $this->assertFileExists(
            $this->sandbox.'/health.json',
        );
    }

    public function test_public_readiness_endpoint_is_minimal_and_does_not_leak_paths_or_database_details(): void
    {
        app(SchedulerHeartbeat::class)->touch();
        app(BackupManager::class)->create();

        $response = $this->getJson('/health/ready');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('critical', 0)
            ->assertJsonMissingPath('checks')
            ->assertHeader(
                'X-Robots-Tag',
                'noindex, nofollow, noarchive',
            );

        $payload = $response->getContent();

        $this->assertIsString($payload);
        $this->assertStringNotContainsString(
            storage_path(),
            $payload,
        );
        $this->assertStringNotContainsString(
            'database',
            strtolower($payload),
        );
    }

    public function test_maintenance_command_keeps_admin_available_and_returns_503_publicly(): void
    {
        $exit = Artisan::call('site:maintenance', [
            'state' => 'on',
            '--message' => 'Pemeliharaan terjadwal.',
        ]);

        $this->assertSame(0, $exit);

        $this->get('/')
            ->assertStatus(503)
            ->assertSee('Pemeliharaan terjadwal.');

        $this->get('/admin/login')
            ->assertOk();

        $exit = Artisan::call('site:maintenance', [
            'state' => 'off',
        ]);

        $this->assertSame(0, $exit);
        $this->get('/')->assertOk();
    }

    public function test_stage_23_operational_commands_are_registered_in_scheduler(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString(
            'operations:heartbeat',
            $output,
        );
        $this->assertStringContainsString(
            'operations:health --snapshot',
            $output,
        );
        $this->assertStringContainsString(
            'operations:backup',
            $output,
        );
        $this->assertStringContainsString(
            'operations:backup:verify',
            $output,
        );
        $this->assertStringContainsString(
            'operations:backup:prune',
            $output,
        );
    }
}
