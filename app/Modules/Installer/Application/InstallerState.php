<?php

namespace App\Modules\Installer\Application;

use RuntimeException;

class InstallerState
{
    public function isInstalled(): bool
    {
        if ((bool) config('installer.force_uninstalled', false)) {
            return false;
        }

        if ((bool) config('installer.force_installed', false)) {
            return true;
        }

        if (is_file($this->lockPath())) {
            return true;
        }

        if ($this->isPending()) {
            return false;
        }

        if (app()->environment('testing')) {
            return true;
        }

        return $this->environmentKeyPresent();
    }

    public function isPending(): bool
    {
        return is_file($this->pendingPath());
    }

    /**
     * @return array<string, mixed>
     */
    public function pending(): array
    {
        $path = $this->pendingPath();

        if (! is_file($path)) {
            return [];
        }

        $json = file_get_contents($path);

        if (! is_string($json) || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function markPending(array $data): void
    {
        $path = $this->pendingPath();
        $this->ensureDirectory(dirname($path));

        $payload = [
            'started_at' => now()->toIso8601String(),
            ...$data,
        ];

        $encoded = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        if (file_put_contents($path, $encoded.PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException(
                'Status installer sementara tidak dapat disimpan.',
            );
        }

        @chmod($path, 0600);
    }

    public function markInstalled(): void
    {
        $path = $this->lockPath();
        $this->ensureDirectory(dirname($path));

        $payload = [
            'installed_at' => now()->toIso8601String(),
            'app_url' => (string) config('app.url'),
        ];

        $encoded = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        $temp = $path.'.tmp-'.bin2hex(random_bytes(6));

        if (file_put_contents($temp, $encoded.PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Lock installer tidak dapat dibuat.');
        }

        @chmod($temp, 0600);

        if (! @rename($temp, $path)) {
            @unlink($temp);

            throw new RuntimeException('Lock installer tidak dapat dipasang.');
        }
    }

    public function finish(): void
    {
        @unlink($this->pendingPath());
        @unlink($this->bootstrapKeyPath());
    }

    public function bootstrapKey(): string
    {
        $path = $this->bootstrapKeyPath();
        $this->ensureDirectory(dirname($path));

        if (is_file($path)) {
            $key = trim((string) file_get_contents($path));

            if ($this->validKey($key)) {
                return $key;
            }
        }

        $key = 'base64:'.base64_encode(random_bytes(32));

        if (file_put_contents($path, $key.PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException(
                'Kunci bootstrap installer tidak dapat disimpan.',
            );
        }

        @chmod($path, 0600);

        return $key;
    }

    private function environmentKeyPresent(): bool
    {
        $envPath = (string) config(
            'installer.env_path',
            base_path('.env'),
        );

        if (is_file($envPath)) {
            $contents = file_get_contents($envPath);

            if (is_string($contents)) {
                foreach (preg_split('/\R/', $contents) ?: [] as $line) {
                    if (! preg_match('/^\s*APP_KEY\s*=\s*(.*)$/', $line, $match)) {
                        continue;
                    }

                    $value = trim((string) ($match[1] ?? ''));
                    $value = trim($value, "'\"");

                    return $value !== '';
                }
            }
        }

        $serverKey = getenv('APP_KEY');

        return is_string($serverKey) && trim($serverKey) !== '';
    }

    private function validKey(string $key): bool
    {
        if (! str_starts_with($key, 'base64:')) {
            return false;
        }

        $decoded = base64_decode(substr($key, 7), true);

        return is_string($decoded) && strlen($decoded) === 32;
    }

    private function lockPath(): string
    {
        return (string) config(
            'installer.lock_path',
            storage_path('app/installed.lock'),
        );
    }

    private function pendingPath(): string
    {
        return (string) config(
            'installer.pending_path',
            storage_path('app/installer/pending.json'),
        );
    }

    private function bootstrapKeyPath(): string
    {
        return (string) config(
            'installer.bootstrap_key_path',
            storage_path('app/installer/bootstrap.key'),
        );
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException(
                "Direktori installer tidak dapat dibuat: {$directory}",
            );
        }
    }
}
