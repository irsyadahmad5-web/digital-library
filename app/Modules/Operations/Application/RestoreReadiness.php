<?php

namespace App\Modules\Operations\Application;

use Symfony\Component\Process\ExecutableFinder;
use Throwable;

class RestoreReadiness
{
    public function __construct(
        private readonly BackupManager $backups,
    ) {}

    /**
     * @return array{
     *     ready: bool,
     *     backup: ?string,
     *     checks: array<int, array{
     *         key: string,
     *         ok: bool,
     *         message: string
     *     }>
     * }
     */
    public function check(?string $backup = null): array
    {
        $checks = [];
        $selected = $backup;

        if (! is_string($selected) || trim($selected) === '') {
            $latest = $this->backups->latest();
            $selected = is_array($latest)
                ? (string) ($latest['name'] ?? '')
                : '';
        }

        if ($selected === '') {
            return [
                'ready' => false,
                'backup' => null,
                'checks' => [[
                    'key' => 'backup',
                    'ok' => false,
                    'message' => 'Belum ada backup yang dapat diverifikasi.',
                ]],
            ];
        }

        try {
            $verification = $this->backups->verify($selected);
            $checks[] = [
                'key' => 'backup',
                'ok' => $verification['valid'],
                'message' => $verification['valid']
                    ? 'Manifest dan checksum backup valid.'
                    : 'Backup gagal verifikasi checksum/kelengkapan.',
            ];
        } catch (Throwable) {
            $checks[] = [
                'key' => 'backup',
                'ok' => false,
                'message' => 'Backup tidak dapat diverifikasi.',
            ];
        }

        $driver = (string) config('database.default');
        $binary = $this->restoreBinary($driver);

        $checks[] = [
            'key' => 'restore_binary',
            'ok' => $binary !== null,
            'message' => $binary !== null
                ? "Tool restore tersedia: {$binary}."
                : "Tool restore untuk driver {$driver} tidak ditemukan.",
        ];

        $writable = $this->storageWritable();

        $checks[] = [
            'key' => 'storage',
            'ok' => $writable,
            'message' => $writable
                ? 'Target storage ebook writable.'
                : 'Salah satu target storage ebook tidak writable.',
        ];

        return [
            'ready' => collect($checks)
                ->every(fn (array $check): bool => $check['ok']),
            'backup' => $selected,
            'checks' => $checks,
        ];
    }

    private function restoreBinary(string $driver): ?string
    {
        if ($driver === 'sqlite') {
            return 'php-file-copy';
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return null;
        }

        $finder = new ExecutableFinder;
        $binary = $finder->find('mariadb')
            ?: $finder->find('mysql');

        return is_string($binary) && $binary !== ''
            ? basename($binary)
            : null;
    }

    private function storageWritable(): bool
    {
        foreach ((array) config('operations.backup.sources', []) as $path) {
            $path = (string) $path;

            if ($path === '') {
                continue;
            }

            if (is_dir($path)) {
                if (! is_writable($path)) {
                    return false;
                }

                continue;
            }

            $parent = dirname($path);

            if (! is_dir($parent) || ! is_writable($parent)) {
                return false;
            }
        }

        return true;
    }
}
