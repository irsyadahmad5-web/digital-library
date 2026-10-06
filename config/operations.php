<?php

return [
    'backup' => [
        'enabled' => (bool) env('BACKUP_ENABLED', true),
        'path' => env(
            'BACKUP_PATH',
            storage_path('app/backups'),
        ),
        'retention_days' => max(
            1,
            (int) env('BACKUP_RETENTION_DAYS', 30),
        ),
        'retention_count' => max(
            1,
            (int) env('BACKUP_RETENTION_COUNT', 14),
        ),
        'max_age_hours' => max(
            1,
            (int) env('BACKUP_MAX_AGE_HOURS', 30),
        ),
        'daily_at' => env('BACKUP_DAILY_AT', '02:30'),
        'sources' => [
            'private/ebooks' => storage_path('app/private/ebooks'),
            'public/ebooks' => storage_path('app/public/ebooks'),
            'public/branding' => storage_path('app/public/branding'),
        ],
    ],

    'health' => [
        'scheduler_max_age_seconds' => max(
            60,
            (int) env('HEALTH_SCHEDULER_MAX_AGE_SECONDS', 180),
        ),
        'disk_warning_percent' => min(
            90,
            max(1, (int) env('HEALTH_DISK_WARNING_PERCENT', 15)),
        ),
        'disk_critical_percent' => min(
            50,
            max(1, (int) env('HEALTH_DISK_CRITICAL_PERCENT', 5)),
        ),
        'heartbeat_path' => env(
            'HEALTH_HEARTBEAT_PATH',
            storage_path('app/operations/scheduler-heartbeat.json'),
        ),
        'snapshot_path' => env(
            'HEALTH_SNAPSHOT_PATH',
            storage_path('app/operations/health.json'),
        ),
    ],
];
