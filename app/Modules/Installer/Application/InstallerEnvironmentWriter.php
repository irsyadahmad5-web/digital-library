<?php

namespace App\Modules\Installer\Application;

use RuntimeException;

class InstallerEnvironmentWriter
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function write(array $values, string $appKey): void
    {
        $path = (string) config(
            'installer.env_path',
            base_path('.env'),
        );

        $template = $this->baseContents($path);
        $this->backupExisting($path);

        $appUrl = rtrim((string) $values['app_url'], '/');
        $host = parse_url($appUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            throw new RuntimeException('APP_URL tidak memiliki host yang valid.');
        }

        $scheme = strtolower(
            (string) parse_url($appUrl, PHP_URL_SCHEME),
        );
        $secure = $scheme === 'https';

        $settings = [
            'APP_NAME' => (string) $values['app_name'],
            'APP_ENV' => 'production',
            'APP_KEY' => $appKey,
            'APP_DEBUG' => 'false',
            'APP_URL' => $appUrl,
            'APP_TIMEZONE' => (string) ($values['timezone'] ?? 'Asia/Jakarta'),
            'APP_LOCALE' => 'id',
            'APP_FALLBACK_LOCALE' => 'en',
            'APP_FAKER_LOCALE' => 'id_ID',
            'DB_CONNECTION' => (string) $values['db_connection'],
            'DB_HOST' => (string) $values['db_host'],
            'DB_PORT' => (string) $values['db_port'],
            'DB_DATABASE' => (string) $values['db_database'],
            'DB_USERNAME' => (string) $values['db_username'],
            'DB_PASSWORD' => (string) ($values['db_password'] ?? ''),
            'SESSION_DRIVER' => 'database',
            'SESSION_ENCRYPT' => 'true',
            'SESSION_SECURE_COOKIE' => $secure ? 'true' : 'false',
            'SESSION_HTTP_ONLY' => 'true',
            'SESSION_SAME_SITE' => 'lax',
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'database',
            'SECURITY_ALLOWED_HOSTS' => strtolower($host),
            'SECURITY_ENFORCE_HOST' => 'true',
            'SECURITY_TRUSTED_PROXIES' => trim(
                (string) ($values['trusted_proxies'] ?? ''),
            ),
            'SECURITY_CSP_ENABLED' => 'true',
            'SECURITY_HSTS_ENABLED' => $secure ? 'true' : 'false',
            'SECURITY_HSTS_MAX_AGE' => '31536000',
            'SECURITY_HSTS_INCLUDE_SUBDOMAINS' => 'false',
            'SECURITY_HSTS_PRELOAD' => 'false',
        ];

        $updated = $this->replaceValues($template, $settings);
        $directory = dirname($path);

        if (! is_dir($directory)) {
            throw new RuntimeException('Direktori .env tidak tersedia.');
        }

        $temp = $path.'.installer-'.bin2hex(random_bytes(6));

        if (file_put_contents($temp, $updated, LOCK_EX) === false) {
            throw new RuntimeException('File .env sementara tidak dapat ditulis.');
        }

        @chmod($temp, 0600);

        if (! @rename($temp, $path)) {
            @unlink($temp);

            throw new RuntimeException('File .env tidak dapat diperbarui secara atomik.');
        }

        @chmod($path, 0600);
    }

    public function cleanupBackup(): void
    {
        $backup = (string) config(
            'installer.env_backup_path',
            storage_path('app/installer/.env.backup'),
        );

        @unlink($backup);
    }

    private function baseContents(string $envPath): string
    {
        if (is_file($envPath)) {
            $contents = file_get_contents($envPath);

            if (is_string($contents)) {
                return $contents;
            }
        }

        $example = base_path('.env.example');

        if (! is_file($example)) {
            throw new RuntimeException(
                '.env.example tidak ditemukan.',
            );
        }

        $contents = file_get_contents($example);

        if (! is_string($contents)) {
            throw new RuntimeException(
                '.env.example tidak dapat dibaca.',
            );
        }

        return $contents;
    }

    private function backupExisting(string $envPath): void
    {
        if (! is_file($envPath)) {
            return;
        }

        $backup = (string) config(
            'installer.env_backup_path',
            storage_path('app/installer/.env.backup'),
        );

        if (is_file($backup)) {
            return;
        }

        $directory = dirname($backup);

        if (
            ! is_dir($directory)
            && ! @mkdir($directory, 0755, true)
            && ! is_dir($directory)
        ) {
            throw new RuntimeException(
                'Direktori backup .env tidak dapat dibuat.',
            );
        }

        if (! @copy($envPath, $backup)) {
            throw new RuntimeException(
                'Backup .env sebelum instalasi gagal dibuat.',
            );
        }

        @chmod($backup, 0600);
    }

    /**
     * @param  array<string, string>  $settings
     */
    private function replaceValues(
        string $contents,
        array $settings,
    ): string {
        $lines = preg_split('/\R/', $contents) ?: [];
        $remaining = $settings;

        foreach ($lines as $index => $line) {
            foreach ($remaining as $key => $value) {
                if (
                    ! preg_match(
                        '/^\s*#?\s*'.preg_quote($key, '/').'\s*=.*$/',
                        $line,
                    )
                ) {
                    continue;
                }

                $lines[$index] = $key.'='.$this->encode($value);
                unset($remaining[$key]);

                break;
            }
        }

        if ($remaining !== []) {
            $lines[] = '';

            foreach ($remaining as $key => $value) {
                $lines[] = $key.'='.$this->encode($value);
            }
        }

        return rtrim(implode(PHP_EOL, $lines)).PHP_EOL;
    }

    private function encode(string $value): string
    {
        if (
            $value !== ''
            && preg_match('/^[A-Za-z0-9_\.\-:\/]+$/', $value)
        ) {
            return $value;
        }

        $escaped = str_replace(
            ['\\', '"', '$', "\r", "\n"],
            ['\\\\', '\\"', '\\$', '', ''],
            $value,
        );

        return '"'.$escaped.'"';
    }
}
