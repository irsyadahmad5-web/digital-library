<?php

namespace App\Modules\Installer\Application;

use DomainException;
use PDO;
use PDOException;
use Throwable;

class InstallerDatabase
{
    /**
     * @param  array<string, mixed>  $values
     * @return array{version: string, database: string, tables: int}
     */
    public function test(array $values): array
    {
        if (! extension_loaded('pdo_mysql')) {
            throw new DomainException('Ekstensi pdo_mysql belum aktif.');
        }

        $database = (string) $values['db_database'];
        $pdo = $this->connect($values);

        try {
            $version = (string) $pdo
                ->query('SELECT VERSION()')
                ->fetchColumn();

            $statement = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.tables '
                .'WHERE table_schema = :database AND table_type = \'BASE TABLE\'',
            );
            $statement->execute(['database' => $database]);
            $tables = (int) $statement->fetchColumn();

            if ($tables > 0) {
                throw new DomainException(
                    "Database {$database} tidak kosong ({$tables} tabel). "
                    .'Gunakan database kosong khusus instalasi ini.',
                );
            }

            $this->probePrivileges($pdo);

            return [
                'version' => $version,
                'database' => $database,
                'tables' => $tables,
            ];
        } finally {
            $pdo = null;
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function connect(array $values): PDO
    {
        $host = (string) $values['db_host'];
        $port = (int) $values['db_port'];
        $database = (string) $values['db_database'];
        $username = (string) $values['db_username'];
        $password = (string) ($values['db_password'] ?? '');

        $dsn = 'mysql:host='.$host
            .';port='.$port
            .';dbname='.$database
            .';charset=utf8mb4';

        try {
            return new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 5,
                ],
            );
        } catch (PDOException $exception) {
            throw new DomainException(
                'Koneksi database gagal. Periksa host, port, nama database, '
                .'username, password, dan hak akses pengguna database.',
                previous: $exception,
            );
        }
    }

    public function currentConnectionReady(): bool
    {
        try {
            $pdo = app('db')->connection()->getPdo();

            return $pdo instanceof PDO;
        } catch (Throwable) {
            return false;
        }
    }

    private function probePrivileges(PDO $pdo): void
    {
        $table = '__digital_library_install_probe_'
            .bin2hex(random_bytes(5));
        $quoted = '`'.str_replace('`', '``', $table).'`';

        try {
            $pdo->exec(
                "CREATE TABLE {$quoted} ("
                .'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, '
                .'probe VARCHAR(32) NULL, '
                .'PRIMARY KEY (id), INDEX idx_probe (probe)'
                .') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            );
            $pdo->exec(
                "ALTER TABLE {$quoted} ADD COLUMN probe_two VARCHAR(16) NULL",
            );
        } catch (Throwable $exception) {
            throw new DomainException(
                'User database tidak memiliki hak CREATE/ALTER yang diperlukan.',
                previous: $exception,
            );
        } finally {
            try {
                $pdo->exec("DROP TABLE IF EXISTS {$quoted}");
            } catch (Throwable) {
                // Best effort; table name is random and contains no application data.
            }
        }
    }
}
