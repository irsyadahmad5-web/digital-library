<?php

namespace App\Modules\Operations\Application;

use RuntimeException;

class SchedulerHeartbeat
{
    public function touch(): void
    {
        $path = $this->path();
        $directory = dirname($path);

        if (
            ! is_dir($directory)
            && ! @mkdir($directory, 0755, true)
            && ! is_dir($directory)
        ) {
            throw new RuntimeException(
                'Direktori heartbeat scheduler tidak dapat dibuat.',
            );
        }

        $payload = json_encode([
            'recorded_at' => now()->toIso8601String(),
            'unix_time' => time(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        if (file_put_contents($path, $payload.PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException(
                'Heartbeat scheduler tidak dapat ditulis.',
            );
        }

        @chmod($path, 0600);
    }

    /**
     * @return array{exists: bool, age_seconds: ?int, recorded_at: ?string}
     */
    public function status(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return [
                'exists' => false,
                'age_seconds' => null,
                'recorded_at' => null,
            ];
        }

        $mtime = filemtime($path);

        if ($mtime === false) {
            return [
                'exists' => true,
                'age_seconds' => null,
                'recorded_at' => null,
            ];
        }

        $payload = json_decode(
            (string) file_get_contents($path),
            true,
        );

        return [
            'exists' => true,
            'age_seconds' => max(0, time() - $mtime),
            'recorded_at' => is_array($payload)
                ? ($payload['recorded_at'] ?? null)
                : null,
        ];
    }

    private function path(): string
    {
        return (string) config(
            'operations.health.heartbeat_path',
            storage_path('app/operations/scheduler-heartbeat.json'),
        );
    }
}
