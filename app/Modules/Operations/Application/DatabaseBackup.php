<?php

namespace App\Modules\Operations\Application;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

class DatabaseBackup
{
    /**
     * @return array{driver: string, file: string, binary: ?string}
     */
    public function dump(string $directory): array
    {
        $driver = (string) config('database.default');

        return match ($driver) {
            'mysql', 'mariadb' => $this->dumpMysqlFamily(
                $directory,
                $driver,
            ),
            'sqlite' => $this->dumpSqlite($directory),
            default => throw new RuntimeException(
                "Backup database belum mendukung driver {$driver}.",
            ),
        };
    }

    /**
     * @return array{driver: string, file: string, binary: string}
     */
    private function dumpMysqlFamily(
        string $directory,
        string $driver,
    ): array {
        $finder = new ExecutableFinder;
        $binary = $finder->find('mariadb-dump')
            ?: $finder->find('mysqldump');

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException(
                'Binary mariadb-dump/mysqldump tidak ditemukan.',
            );
        }

        $connection = (array) config(
            "database.connections.{$driver}",
            [],
        );
        $database = (string) ($connection['database'] ?? '');
        $username = (string) ($connection['username'] ?? '');
        $password = (string) ($connection['password'] ?? '');
        $host = (string) ($connection['host'] ?? '127.0.0.1');
        $port = (int) ($connection['port'] ?? 3306);
        $socket = (string) ($connection['unix_socket'] ?? '');

        if ($database === '' || $username === '') {
            throw new RuntimeException(
                'Konfigurasi database backup belum lengkap.',
            );
        }

        $credentials = $directory.'/.database-client.cnf';
        $dumpPath = $directory.'/database.sql';

        $lines = [
            '[client]',
            'user='.$this->optionValue($username),
            'password='.$this->optionValue($password),
            'host='.$this->optionValue($host),
            'port='.$port,
            'default-character-set=utf8mb4',
        ];

        if ($socket !== '') {
            $lines[] = 'socket='.$this->optionValue($socket);
        }

        if (
            file_put_contents(
                $credentials,
                implode(PHP_EOL, $lines).PHP_EOL,
                LOCK_EX,
            ) === false
        ) {
            throw new RuntimeException(
                'File credential sementara untuk backup database gagal dibuat.',
            );
        }

        @chmod($credentials, 0600);

        $stderr = '';
        $stdoutHandle = fopen($dumpPath, 'wb');

        if ($stdoutHandle === false) {
            @unlink($credentials);

            throw new RuntimeException(
                'File dump database tidak dapat dibuat.',
            );
        }

        $command = [
            $binary,
            '--defaults-extra-file='.$credentials,
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--routines',
            '--events',
            '--triggers',
            '--hex-blob',
            '--default-character-set=utf8mb4',
            '--databases',
            $database,
        ];

        try {
            $descriptors = [
                0 => ['file', '/dev/null', 'r'],
                1 => $stdoutHandle,
                2 => ['pipe', 'w'],
            ];

            $process = proc_open(
                $command,
                $descriptors,
                $pipes,
                base_path(),
                null,
                ['bypass_shell' => true],
            );

            if (! is_resource($process)) {
                throw new RuntimeException(
                    'Proses dump database tidak dapat dijalankan.',
                );
            }

            $stderr = stream_get_contents($pipes[2]) ?: '';
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                throw new RuntimeException(
                    'Dump database gagal'
                    .($stderr !== ''
                        ? ': '.mb_substr(trim($stderr), 0, 1000)
                        : '.'),
                );
            }
        } finally {
            fclose($stdoutHandle);
            @unlink($credentials);
        }

        if (! is_file($dumpPath) || filesize($dumpPath) === 0) {
            throw new RuntimeException(
                'Dump database kosong atau tidak terbentuk.',
            );
        }

        @chmod($dumpPath, 0600);

        return [
            'driver' => $driver,
            'file' => 'database.sql',
            'binary' => basename($binary),
        ];
    }

    /**
     * @return array{driver: string, file: string, binary: null}
     */
    private function dumpSqlite(string $directory): array
    {
        $source = (string) config(
            'database.connections.sqlite.database',
            '',
        );
        $target = $directory.'/database.sqlite';

        if (
            $source === ''
            || $source === ':memory:'
            || ! is_file($source)
        ) {
            throw new RuntimeException(
                'Backup SQLite memerlukan database berbasis file.',
            );
        }

        if (! @copy($source, $target)) {
            throw new RuntimeException(
                'Database SQLite tidak dapat disalin.',
            );
        }

        @chmod($target, 0600);

        return [
            'driver' => 'sqlite',
            'file' => 'database.sqlite',
            'binary' => null,
        ];
    }

    private function optionValue(string $value): string
    {
        return '"'.str_replace(
            ['\\', '"', "\r", "\n"],
            ['\\\\', '\\"', '', ''],
            $value,
        ).'"';
    }
}
