<?php

namespace App\Modules\Installer\Application;

use App\Enums\UserStatus;
use App\Models\User;
use App\Modules\Audit\Application\AuditLogger;
use App\Modules\Identity\Domain\Models\Role;
use App\Modules\Settings\Application\SettingsManager;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class InstallerManager
{
    public function __construct(
        private readonly InstallerState $state,
        private readonly InstallerRequirementChecker $requirements,
        private readonly InstallerDatabase $database,
        private readonly InstallerEnvironmentWriter $environment,
        private readonly SettingsManager $settings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     * @return array{version: string, database: string, tables: int}
     */
    public function prepareDatabase(array $values): array
    {
        $requirementStatus = $this->requirements->check();

        if (! $requirementStatus['passed']) {
            throw new DomainException(
                'Requirement wajib belum terpenuhi. Perbaiki server sebelum melanjutkan.',
            );
        }

        $result = $this->database->test($values);

        $pending = [
            'app_name' => (string) $values['app_name'],
            'app_url' => rtrim((string) $values['app_url'], '/'),
            'timezone' => (string) ($values['timezone'] ?? 'Asia/Jakarta'),
            'db_connection' => (string) $values['db_connection'],
            'db_host' => (string) $values['db_host'],
            'db_port' => (int) $values['db_port'],
            'db_database' => (string) $values['db_database'],
            'db_username' => (string) $values['db_username'],
            'db_version' => $result['version'],
            'trusted_proxies' => trim(
                (string) ($values['trusted_proxies'] ?? ''),
            ),
        ];

        $this->state->markPending([
            ...$pending,
            'environment_written' => false,
        ]);

        try {
            $this->environment->write(
                $values,
                $this->state->bootstrapKey(),
            );

            Artisan::call('config:clear');

            $this->state->markPending([
                ...$pending,
                'environment_written' => true,
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Konfigurasi database berhasil diuji, tetapi .env gagal diperbarui: '
                .$exception->getMessage(),
                previous: $exception,
            );
        }

        return $result;
    }

    /**
     * @param  array{name: string, email: string, password: string}  $admin
     * @return array{user: User, migrations: string, seeder: string}
     */
    public function complete(array $admin): array
    {
        $pending = $this->state->pending();

        if (
            ! $this->state->isPending()
            || ($pending['environment_written'] ?? false) !== true
        ) {
            throw new DomainException(
                'Konfigurasi database belum diselesaikan.',
            );
        }

        $lockHandle = $this->acquireProcessLock();

        try {
            if ($this->state->isInstalled()) {
                throw new DomainException('Aplikasi sudah terinstal.');
            }

            if (! $this->database->currentConnectionReady()) {
                throw new DomainException(
                    'Database tidak dapat dihubungi menggunakan konfigurasi .env.',
                );
            }

            $migrations = $this->runArtisan('migrate', [
                '--force' => true,
                '--no-interaction' => true,
            ]);

            $seeder = $this->runArtisan('db:seed', [
                '--class' => DatabaseSeeder::class,
                '--force' => true,
                '--no-interaction' => true,
            ]);

            $this->ensureStorageLink();

            $user = $this->createOrRecoverSuperAdmin($admin);
            $pending = $this->state->pending();

            $siteName = trim(
                (string) ($pending['app_name'] ?? config('app.name')),
            );

            if ($siteName !== '') {
                $this->settings->updateGroup(
                    'general',
                    [
                        'site_name' => $siteName,
                        'short_name' => mb_substr($siteName, 0, 60),
                    ],
                    $user->getKey(),
                );
            }

            $this->audit->log(
                'installation.completed',
                actor: $user,
                subjectType: 'installation',
                subjectId: 'initial',
                metadata: [
                    'database_driver' => (string) config(
                        'database.default',
                    ),
                    'app_url' => (string) config('app.url'),
                ],
                request: request(),
            );

            $this->state->markInstalled();
            $this->environment->cleanupBackup();
            $this->state->finish();

            return [
                'user' => $user,
                'migrations' => $migrations,
                'seeder' => $seeder,
            ];
        } finally {
            $this->releaseProcessLock($lockHandle);
        }
    }

    /**
     * @param  array{name: string, email: string, password: string}  $admin
     */
    private function createOrRecoverSuperAdmin(array $admin): User
    {
        $existingCount = User::query()->count();
        $email = mb_strtolower(trim($admin['email']));

        if ($existingCount > 1) {
            throw new DomainException(
                'Database berisi lebih dari satu user sebelum installer selesai. '
                .'Gunakan database baru yang bersih.',
            );
        }

        return DB::transaction(function () use (
            $existingCount,
            $email,
            $admin,
        ): User {
            $user = User::query()->where('email', $email)->first();

            if ($existingCount === 1 && ! $user) {
                throw new DomainException(
                    'Database sudah memiliki user yang berbeda. '
                    .'Installer tidak akan menimpa akun tersebut.',
                );
            }

            $user ??= new User;

            $user->forceFill([
                'name' => trim($admin['name']),
                'email' => $email,
                'email_verified_at' => now(),
                'password' => $admin['password'],
                'status' => UserStatus::Active,
                'password_changed_at' => now(),
                'force_password_change' => false,
            ])->save();

            $role = Role::query()
                ->where('slug', 'super-admin')
                ->firstOrFail();

            $user->roles()->syncWithoutDetaching([
                $role->getKey(),
            ]);

            return $user->fresh();
        });
    }

    private function ensureStorageLink(): void
    {
        $link = public_path('storage');
        $target = storage_path('app/public');

        if (! is_dir($target) && ! @mkdir($target, 0755, true) && ! is_dir($target)) {
            throw new RuntimeException(
                'Direktori storage/app/public tidak dapat dibuat.',
            );
        }

        if (is_link($link)) {
            return;
        }

        if (file_exists($link)) {
            throw new RuntimeException(
                'public/storage sudah ada tetapi bukan symbolic link. '
                .'Pindahkan/hapus path tersebut lalu ulangi instalasi.',
            );
        }

        $output = $this->runArtisan('storage:link', [
            '--no-interaction' => true,
        ]);

        if (! is_link($link)) {
            throw new RuntimeException(
                'Symbolic link public/storage gagal dibuat. '.$output,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function runArtisan(
        string $command,
        array $arguments,
    ): string {
        $exitCode = Artisan::call($command, $arguments);
        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            throw new RuntimeException(
                "Artisan {$command} gagal"
                .($output !== '' ? ': '.$output : '.'),
            );
        }

        return $output;
    }

    /**
     * @return resource
     */
    private function acquireProcessLock()
    {
        $path = storage_path('app/installer/process.lock');
        $directory = dirname($path);

        if (
            ! is_dir($directory)
            && ! @mkdir($directory, 0755, true)
            && ! is_dir($directory)
        ) {
            throw new RuntimeException(
                'Direktori lock installer tidak dapat dibuat.',
            );
        }

        $handle = fopen($path, 'c+');

        if ($handle === false) {
            throw new RuntimeException(
                'Lock proses installer tidak dapat dibuka.',
            );
        }

        if (! flock($handle, LOCK_EX)) {
            fclose($handle);

            throw new RuntimeException(
                'Lock proses installer tidak dapat diperoleh.',
            );
        }

        return $handle;
    }

    /**
     * @param  resource  $handle
     */
    private function releaseProcessLock($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
