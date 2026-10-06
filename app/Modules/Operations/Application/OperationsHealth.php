<?php

namespace App\Modules\Operations\Application;

use App\Modules\Installer\Application\InstallerState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class OperationsHealth
{
    public function __construct(
        private readonly InstallerState $installer,
        private readonly SchedulerHeartbeat $heartbeat,
        private readonly BackupManager $backups,
    ) {}

    /**
     * @return array{
     *     status: string,
     *     checked_at: string,
     *     counts: array{ok: int, warning: int, critical: int},
     *     checks: array<int, array<string, mixed>>
     * }
     */
    public function report(): array
    {
        $checks = [
            $this->installedCheck(),
            $this->databaseCheck(),
            $this->migrationCheck(),
            $this->storageCheck(),
            $this->diskCheck(),
            $this->schedulerCheck(),
            $this->backupCheck(),
            $this->failedJobsCheck(),
            $this->productionDebugCheck(),
        ];

        $counts = [
            'ok' => 0,
            'warning' => 0,
            'critical' => 0,
        ];

        foreach ($checks as $check) {
            $level = $check['level'] ?? 'critical';

            if (array_key_exists($level, $counts)) {
                $counts[$level]++;
            }
        }

        $status = $counts['critical'] > 0
            ? 'unhealthy'
            : ($counts['warning'] > 0 ? 'degraded' : 'ok');

        return [
            'status' => $status,
            'checked_at' => now()->toIso8601String(),
            'counts' => $counts,
            'checks' => $checks,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function writeSnapshot(array $report): void
    {
        $path = (string) config(
            'operations.health.snapshot_path',
            storage_path('app/operations/health.json'),
        );
        $directory = dirname($path);

        if (
            ! is_dir($directory)
            && ! @mkdir($directory, 0755, true)
            && ! is_dir($directory)
        ) {
            throw new RuntimeException(
                'Direktori snapshot health tidak dapat dibuat.',
            );
        }

        $payload = json_encode(
            $report,
            JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR,
        );

        if (
            file_put_contents(
                $path,
                $payload.PHP_EOL,
                LOCK_EX,
            ) === false
        ) {
            throw new RuntimeException(
                'Snapshot health tidak dapat ditulis.',
            );
        }

        @chmod($path, 0600);
    }

    /**
     * @return array<string, mixed>
     */
    private function installedCheck(): array
    {
        return $this->check(
            'installation',
            'Installation lock',
            $this->installer->isInstalled(),
            'critical',
            $this->installer->isInstalled()
                ? 'Aplikasi terinstal.'
                : 'Installer belum selesai atau lock instalasi tidak tersedia.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseCheck(): array
    {
        try {
            $started = microtime(true);
            DB::connection()->select('SELECT 1');
            $milliseconds = round(
                (microtime(true) - $started) * 1000,
                2,
            );

            return $this->check(
                'database',
                'Database connectivity',
                true,
                'critical',
                "Database merespons dalam {$milliseconds} ms.",
                ['latency_ms' => $milliseconds],
            );
        } catch (Throwable $exception) {
            return $this->check(
                'database',
                'Database connectivity',
                false,
                'critical',
                'Database tidak dapat dihubungi.',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function migrationCheck(): array
    {
        try {
            if (! Schema::hasTable('migrations')) {
                return $this->check(
                    'migrations',
                    'Database migrations',
                    false,
                    'critical',
                    'Tabel migrations belum tersedia.',
                );
            }

            $ran = DB::table('migrations')
                ->pluck('migration')
                ->all();
            $files = glob(database_path('migrations/*.php')) ?: [];
            $available = array_map(
                static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
                $files,
            );
            $pending = array_values(
                array_diff($available, $ran),
            );

            return $this->check(
                'migrations',
                'Database migrations',
                $pending === [],
                'critical',
                $pending === []
                    ? 'Semua migration sudah terpasang.'
                    : count($pending).' migration belum dijalankan.',
                [
                    'pending_count' => count($pending),
                ],
            );
        } catch (Throwable) {
            return $this->check(
                'migrations',
                'Database migrations',
                false,
                'critical',
                'Status migration tidak dapat diperiksa.',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function storageCheck(): array
    {
        $paths = [
            storage_path('app/private'),
            storage_path('app/public'),
            storage_path('framework'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];

        $notWritable = array_values(array_filter(
            $paths,
            static fn (string $path): bool => ! is_dir($path) || ! is_writable($path),
        ));

        $storageLink = public_path('storage');
        $linkOk = is_link($storageLink)
            || app()->environment(['local', 'testing']);

        $ok = $notWritable === [] && $linkOk;

        return $this->check(
            'storage',
            'Storage & public link',
            $ok,
            'critical',
            $ok
                ? 'Storage writable dan public/storage tersedia.'
                : 'Storage/link belum siap.',
            [
                'not_writable_count' => count($notWritable),
                'public_link' => $linkOk,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function diskCheck(): array
    {
        $path = storage_path();
        $free = disk_free_space($path);
        $total = disk_total_space($path);

        if (
            ! is_int($free)
            && ! is_float($free)
        ) {
            return $this->check(
                'disk',
                'Disk free space',
                false,
                'warning',
                'Kapasitas disk tidak dapat dibaca.',
            );
        }

        if (
            (! is_int($total) && ! is_float($total))
            || $total <= 0
        ) {
            return $this->check(
                'disk',
                'Disk free space',
                false,
                'warning',
                'Kapasitas total disk tidak dapat dibaca.',
            );
        }

        $percent = round(($free / $total) * 100, 2);
        $warning = (int) config(
            'operations.health.disk_warning_percent',
            15,
        );
        $critical = (int) config(
            'operations.health.disk_critical_percent',
            5,
        );

        $level = $percent <= $critical
            ? 'critical'
            : ($percent <= $warning ? 'warning' : 'ok');

        return $this->check(
            'disk',
            'Disk free space',
            $level === 'ok',
            $level,
            "{$percent}% ruang disk tersedia.",
            [
                'free_bytes' => (int) $free,
                'total_bytes' => (int) $total,
                'free_percent' => $percent,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function schedulerCheck(): array
    {
        $status = $this->heartbeat->status();
        $maxAge = (int) config(
            'operations.health.scheduler_max_age_seconds',
            180,
        );

        if (! $status['exists']) {
            return $this->check(
                'scheduler',
                'Scheduler heartbeat',
                false,
                app()->environment('production')
                    ? 'critical'
                    : 'warning',
                'Heartbeat scheduler belum pernah tercatat.',
                $status,
            );
        }

        $age = $status['age_seconds'];

        if (! is_int($age)) {
            return $this->check(
                'scheduler',
                'Scheduler heartbeat',
                false,
                'warning',
                'Umur heartbeat scheduler tidak dapat dibaca.',
                $status,
            );
        }

        $level = $age > ($maxAge * 3)
            ? 'critical'
            : ($age > $maxAge ? 'warning' : 'ok');

        return $this->check(
            'scheduler',
            'Scheduler heartbeat',
            $level === 'ok',
            $level,
            "Heartbeat scheduler berumur {$age} detik.",
            $status,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function backupCheck(): array
    {
        if (! (bool) config('operations.backup.enabled', true)) {
            return $this->check(
                'backup',
                'Latest backup',
                true,
                'ok',
                'Backup otomatis dinonaktifkan secara eksplisit.',
                ['enabled' => false],
            );
        }

        try {
            $latest = $this->backups->latest();
        } catch (Throwable) {
            return $this->check(
                'backup',
                'Latest backup',
                false,
                'warning',
                'Status backup tidak dapat dibaca.',
            );
        }

        if ($latest === null) {
            return $this->check(
                'backup',
                'Latest backup',
                false,
                'warning',
                'Belum ada backup yang berhasil.',
            );
        }

        $timestamp = strtotime(
            (string) ($latest['created_at'] ?? ''),
        );

        if ($timestamp === false) {
            return $this->check(
                'backup',
                'Latest backup',
                false,
                'warning',
                'Timestamp backup terakhir tidak valid.',
            );
        }

        $ageHours = round(
            (time() - $timestamp) / 3600,
            2,
        );
        $maxAge = (int) config(
            'operations.backup.max_age_hours',
            30,
        );
        $level = $ageHours > ($maxAge * 2)
            ? 'critical'
            : ($ageHours > $maxAge ? 'warning' : 'ok');

        return $this->check(
            'backup',
            'Latest backup',
            $level === 'ok',
            $level,
            "Backup terakhir berumur {$ageHours} jam.",
            [
                'name' => $latest['name'],
                'age_hours' => $ageHours,
                'total_bytes' => $latest['total_bytes'],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function failedJobsCheck(): array
    {
        try {
            if (! Schema::hasTable('failed_jobs')) {
                return $this->check(
                    'failed_jobs',
                    'Failed queue jobs',
                    true,
                    'ok',
                    'Tabel failed_jobs belum tersedia.',
                    ['count' => 0],
                );
            }

            $count = DB::table('failed_jobs')->count();

            return $this->check(
                'failed_jobs',
                'Failed queue jobs',
                $count === 0,
                $count === 0 ? 'ok' : 'warning',
                $count === 0
                    ? 'Tidak ada failed job.'
                    : "{$count} failed job perlu ditinjau.",
                ['count' => $count],
            );
        } catch (Throwable) {
            return $this->check(
                'failed_jobs',
                'Failed queue jobs',
                false,
                'warning',
                'Status failed job tidak dapat dibaca.',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function productionDebugCheck(): array
    {
        $unsafe = app()->environment('production')
            && (bool) config('app.debug');

        return $this->check(
            'production_debug',
            'Production debug mode',
            ! $unsafe,
            'critical',
            $unsafe
                ? 'APP_DEBUG aktif di production.'
                : 'Debug mode aman untuk environment saat ini.',
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function check(
        string $key,
        string $label,
        bool $ok,
        string $level,
        string $message,
        array $meta = [],
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'ok' => $ok,
            'level' => $ok ? 'ok' : $level,
            'message' => $message,
            'meta' => $meta,
        ];
    }
}
