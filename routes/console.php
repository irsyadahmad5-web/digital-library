<?php

use App\Modules\Library\Application\Pdf\PdfProcessingManager;
use App\Modules\Library\Application\Storage\ChunkUploadManager;
use App\Modules\Library\Application\Storage\EbookFileManager;
use App\Modules\Operations\Application\BackupManager;
use App\Modules\Operations\Application\OperationsHealth;
use App\Modules\Operations\Application\RestoreReadiness;
use App\Modules\Operations\Application\SchedulerHeartbeat;
use App\Modules\Quality\Application\ReleaseQualityGate;
use App\Modules\Release\Application\ProductionReleaseVerifier;
use App\Modules\Settings\Application\SettingsManager;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ebooks:uploads:cleanup', function () {
    $count = app(ChunkUploadManager::class)->cleanupAllExpired();

    $this->info("Cleaned {$count} expired ebook upload session(s).");
})->purpose('Remove expired ebook upload chunks and sessions');

Artisan::command('ebooks:external:verify', function () {
    $result = app(EbookFileManager::class)->reverifyDueExternalSources();

    $this->info(
        "Checked {$result['checked']} external source(s): "
        ."{$result['verified']} verified, {$result['failed']} failed.",
    );
})->purpose('Reverify due external ebook PDF sources');

Artisan::command('ebooks:pdf:process {--limit=5} {--ebook=}', function () {
    $limit = max(1, min(50, (int) $this->option('limit')));
    $ebook = $this->option('ebook');
    $ebookId = is_numeric($ebook) ? (int) $ebook : null;

    $result = app(PdfProcessingManager::class)->processPending(
        $limit,
        $ebookId,
    );

    $this->info(
        "Checked {$result['checked']} PDF source(s): "
        ."{$result['processed']} processed, {$result['failed']} failed.",
    );
})->purpose('Process pending ebook PDF metadata and first-page previews');

Artisan::command('operations:heartbeat', function () {
    app(SchedulerHeartbeat::class)->touch();

    $this->info('Scheduler heartbeat recorded.');
})->purpose('Record scheduler heartbeat used by operational health checks');

Artisan::command('operations:backup', function () {
    $manifest = app(BackupManager::class)->create();

    $this->info(
        "Backup {$manifest['name']} completed: "
        ."{$manifest['file_count']} file(s), "
        ."{$manifest['total_bytes']} byte(s).",
    );
})->purpose('Create an atomic database and ebook storage backup');

Artisan::command('operations:backup:list', function () {
    $backups = app(BackupManager::class)->list();

    if ($backups === []) {
        $this->info('No completed backup is available.');

        return 0;
    }

    $this->table(
        ['Name', 'Created', 'Files', 'Bytes', 'Database'],
        array_map(
            static fn (array $backup): array => [
                $backup['name'],
                $backup['created_at'] ?? '-',
                $backup['file_count'],
                $backup['total_bytes'],
                $backup['database_driver'] ?? '-',
            ],
            $backups,
        ),
    );

    return 0;
})->purpose('List completed local backup snapshots');

Artisan::command('operations:backup:verify {backup?}', function () {
    $manager = app(BackupManager::class);
    $name = $this->argument('backup');

    if (! is_string($name) || trim($name) === '') {
        $latest = $manager->latest();

        if ($latest === null) {
            $this->error('No completed backup is available.');

            return 1;
        }

        $name = (string) $latest['name'];
    }

    $result = $manager->verify($name);

    if (! $result['valid']) {
        $this->error("Backup {$name} failed verification.");

        foreach ($result['errors'] as $error) {
            $this->line('- '.$error);
        }

        return 1;
    }

    $this->info(
        "Backup {$name} verified: "
        ."{$result['checked_files']} file(s), "
        ."{$result['total_bytes']} byte(s).",
    );

    return 0;
})->purpose('Verify checksums and completeness of a backup snapshot');

Artisan::command('operations:restore:check {backup?}', function () {
    $name = $this->argument('backup');
    $result = app(RestoreReadiness::class)->check(
        is_string($name) ? $name : null,
    );

    $this->info(
        'Restore readiness for '
        .($result['backup'] ?? 'no backup')
        .': '.($result['ready'] ? 'READY' : 'NOT READY'),
    );

    foreach ($result['checks'] as $check) {
        $this->line(
            ($check['ok'] ? '[OK] ' : '[FAIL] ')
            .$check['message'],
        );
    }

    return $result['ready'] ? 0 : 1;
})->purpose('Validate backup integrity and restore prerequisites');

Artisan::command('operations:backup:prune', function () {
    $result = app(BackupManager::class)->prune();

    $this->info(
        'Backup retention complete: '
        .count($result['deleted']).' deleted, '
        .count($result['kept']).' kept.',
    );
})->purpose('Prune local backups according to retention policy');

Artisan::command('operations:health {--json} {--snapshot}', function () {
    $health = app(OperationsHealth::class);
    $report = $health->report();

    if ((bool) $this->option('snapshot')) {
        $health->writeSnapshot($report);
    }

    if ((bool) $this->option('json')) {
        $this->line(json_encode(
            $report,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        ));
    } else {
        $this->info(
            'Overall health: '.strtoupper($report['status']),
        );

        foreach ($report['checks'] as $check) {
            $prefix = match ($check['level']) {
                'ok' => '[OK]',
                'warning' => '[WARN]',
                default => '[CRIT]',
            };

            $this->line(
                "{$prefix} {$check['label']}: {$check['message']}",
            );
        }
    }

    return $report['counts']['critical'] > 0 ? 1 : 0;
})->purpose('Run operational readiness checks and optionally save a snapshot');

Artisan::command('quality:verify {--production} {--json}', function () {
    $result = app(ReleaseQualityGate::class)->verify(
        (bool) $this->option('production'),
    );

    if ((bool) $this->option('json')) {
        $this->line(json_encode(
            $result,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        ));

        return $result['passed'] ? 0 : 1;
    }

    $this->info(
        'Release quality gate: '
        .($result['passed'] ? 'PASS' : 'FAIL'),
    );

    foreach ($result['checks'] as $check) {
        $this->line(
            ($check['passed'] ? '[PASS] ' : '[FAIL] ')
            .$check['key'].': '.$check['message'],
        );
    }

    return $result['passed'] ? 0 : 1;
})->purpose('Verify release artifacts, toolchain, storage, and production safety');

Artisan::command('release:info {--json}', function () {
    $version = (string) config('release.version', '0.0.0-dev');
    $channel = (string) config('release.channel', 'stable');
    $payload = [
        'version' => $version,
        'channel' => $channel,
    ];

    if ((bool) $this->option('json')) {
        $this->line(json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        ));

        return 0;
    }

    $this->info("Digital Library {$version} ({$channel})");

    return 0;
})->purpose('Show the application release version and channel');

Artisan::command('release:verify {--fresh-install} {--json}', function () {
    $result = app(ProductionReleaseVerifier::class)->verify(
        (bool) $this->option('fresh-install'),
    );

    if ((bool) $this->option('json')) {
        $this->line(json_encode(
            $result,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        ));

        return $result['passed'] ? 0 : 1;
    }

    $this->info(
        'Production release '
        .$result['version'].' verification: '
        .($result['passed'] ? 'PASS' : 'FAIL'),
    );

    foreach ($result['checks'] as $check) {
        $this->line(
            ($check['passed'] ? '[PASS] ' : '[FAIL] ')
            .$check['key'].': '.$check['message'],
        );
    }

    return $result['passed'] ? 0 : 1;
})->purpose('Run final production release, operations, and restore-readiness gates');

Artisan::command(
    'site:maintenance {state=status} {--message=}',
    function () {
        $state = strtolower((string) $this->argument('state'));
        $settings = app(SettingsManager::class);
        $current = $settings->group('maintenance');

        if ($state === 'status') {
            $this->info(
                ($current['enabled'] ?? false)
                    ? 'Public maintenance mode is ON.'
                    : 'Public maintenance mode is OFF.',
            );

            return 0;
        }

        if (! in_array($state, ['on', 'off'], true)) {
            $this->error('State must be one of: status, on, off.');

            return 1;
        }

        $values = [
            'enabled' => $state === 'on',
        ];

        $message = trim((string) $this->option('message'));

        if ($message !== '') {
            $values['message'] = mb_substr($message, 0, 500);
        }

        $settings->updateGroup(
            'maintenance',
            $values,
            null,
        );

        $this->info(
            $state === 'on'
                ? 'Public maintenance mode enabled.'
                : 'Public maintenance mode disabled.',
        );

        return 0;
    },
)->purpose('Show, enable, or disable public maintenance mode');

Schedule::command('operations:heartbeat')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('operations:health --snapshot')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('operations:backup')
    ->dailyAt((string) config(
        'operations.backup.daily_at',
        '02:30',
    ))
    ->when(fn (): bool => (bool) config(
        'operations.backup.enabled',
        true,
    ))
    ->withoutOverlapping(180);

Schedule::command('operations:backup:verify')
    ->dailyAt('04:00')
    ->when(fn (): bool => (bool) config(
        'operations.backup.enabled',
        true,
    ))
    ->withoutOverlapping(180);

Schedule::command('operations:backup:prune')
    ->dailyAt('04:30')
    ->when(fn (): bool => (bool) config(
        'operations.backup.enabled',
        true,
    ))
    ->withoutOverlapping();

Schedule::command('ebooks:uploads:cleanup')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('ebooks:external:verify')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('ebooks:pdf:process --limit=5')
    ->everyMinute()
    ->withoutOverlapping();
