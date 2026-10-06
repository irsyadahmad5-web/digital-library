<?php

namespace App\Modules\Quality\Application;

use App\Modules\Installer\Application\InstallerState;
use App\Modules\Library\Application\Pdf\PdfToolchain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

class ReleaseQualityGate
{
    public function __construct(
        private readonly PdfToolchain $pdfToolchain,
        private readonly InstallerState $installerState,
    ) {}

    /**
     * @return array{
     *     passed: bool,
     *     production: bool,
     *     checks: array<int, array{
     *         key: string,
     *         passed: bool,
     *         message: string
     *     }>
     * }
     */
    public function verify(bool $production = false): array
    {
        $checks = [
            $this->booleanCheck(
                'php',
                version_compare(PHP_VERSION, '8.3.0', '>='),
                'PHP '.PHP_VERSION.' (minimum 8.3).',
            ),
            $this->booleanCheck(
                'composer_autoload',
                is_file(base_path('vendor/autoload.php')),
                'Composer autoload is available.',
            ),
            $this->databaseCheck(),
            $this->publicArtifactsCheck(),
            $this->viteManifestCheck(),
            $this->pwaIconsCheck(),
            $this->storageCheck(),
            $this->pdfToolchainCheck(),
            $this->databaseToolsCheck(),
        ];

        if ($production) {
            array_push(
                $checks,
                ...$this->productionChecks(),
            );
        }

        return [
            'passed' => collect($checks)
                ->every(fn (array $check): bool => $check['passed']),
            'production' => $production,
            'checks' => $checks,
        ];
    }

    /**
     * @return array{key: string, passed: bool, message: string}
     */
    private function databaseCheck(): array
    {
        try {
            DB::connection()->select('SELECT 1');

            return $this->booleanCheck(
                'database',
                true,
                'Database connection is available.',
            );
        } catch (Throwable) {
            return $this->booleanCheck(
                'database',
                false,
                'Database connection failed.',
            );
        }
    }

    /**
     * @return array{key: string, passed: bool, message: string}
     */
    private function publicArtifactsCheck(): array
    {
        $missing = [];

        foreach ((array) config('quality.required_public_files', []) as $relative) {
            $relative = ltrim((string) $relative, '/');

            if ($relative === '' || ! is_file(public_path($relative))) {
                $missing[] = $relative;
            }
        }

        return $this->booleanCheck(
            'public_artifacts',
            $missing === [],
            $missing === []
                ? 'Required public release artifacts are present.'
                : 'Missing public artifacts: '.implode(', ', $missing),
        );
    }

    /**
     * @return array{key: string, passed: bool, message: string}
     */
    private function viteManifestCheck(): array
    {
        $path = public_path('build/manifest.json');

        if (! is_file($path)) {
            return $this->booleanCheck(
                'vite_manifest',
                false,
                'Vite build manifest is missing.',
            );
        }

        $decoded = json_decode(
            (string) file_get_contents($path),
            true,
        );

        if (! is_array($decoded) || $decoded === []) {
            return $this->booleanCheck(
                'vite_manifest',
                false,
                'Vite build manifest is invalid or empty.',
            );
        }

        $missing = [];

        foreach ($decoded as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach (['file', 'css'] as $field) {
                $values = $field === 'file'
                    ? [$entry[$field] ?? null]
                    : ($entry[$field] ?? []);

                foreach ((array) $values as $relative) {
                    if (
                        is_string($relative)
                        && $relative !== ''
                        && ! is_file(public_path('build/'.$relative))
                    ) {
                        $missing[] = $relative;
                    }
                }
            }
        }

        return $this->booleanCheck(
            'vite_manifest',
            $missing === [],
            $missing === []
                ? 'Vite manifest references existing build assets.'
                : 'Vite manifest references missing assets: '
                    .implode(', ', array_slice(array_unique($missing), 0, 10)),
        );
    }

    /**
     * @return array{key: string, passed: bool, message: string}
     */
    private function pwaIconsCheck(): array
    {
        $invalid = [];

        foreach ((array) config('quality.pwa_icons', []) as $relative => $expected) {
            $path = public_path((string) $relative);
            $size = is_file($path) ? @getimagesize($path) : false;

            if (
                ! is_array($size)
                || ($size['mime'] ?? null) !== 'image/png'
                || $size[0] !== (int) ($expected[0] ?? 0)
                || $size[1] !== (int) ($expected[1] ?? 0)
            ) {
                $invalid[] = (string) $relative;
            }
        }

        return $this->booleanCheck(
            'pwa_icons',
            $invalid === [],
            $invalid === []
                ? 'PWA icons have the expected PNG dimensions.'
                : 'Invalid PWA icons: '.implode(', ', $invalid),
        );
    }

    /**
     * @return array{key: string, passed: bool, message: string}
     */
    private function storageCheck(): array
    {
        $invalid = [];

        foreach ((array) config('quality.writable_paths', []) as $path) {
            $path = (string) $path;

            if (! is_dir($path) || ! is_writable($path)) {
                $invalid[] = $path;
            }
        }

        return $this->booleanCheck(
            'writable_paths',
            $invalid === [],
            $invalid === []
                ? 'Runtime storage paths are writable.'
                : count($invalid).' runtime path(s) are not writable.',
        );
    }

    /**
     * @return array{key: string, passed: bool, message: string}
     */
    private function pdfToolchainCheck(): array
    {
        $availability = $this->pdfToolchain->availability();
        $ready = collect($availability)
            ->every(fn (string|false $binary): bool => is_string($binary) && $binary !== '');

        return $this->booleanCheck(
            'pdf_toolchain',
            $ready,
            $ready
                ? 'pdfinfo and pdftocairo are available.'
                : 'pdfinfo and/or pdftocairo are unavailable.',
        );
    }

    /**
     * @return array{key: string, passed: bool, message: string}
     */
    private function databaseToolsCheck(): array
    {
        $finder = new ExecutableFinder;
        $dump = $finder->find('mariadb-dump')
            ?: $finder->find('mysqldump');
        $restore = $finder->find('mariadb')
            ?: $finder->find('mysql');
        $ready = is_string($dump)
            && $dump !== ''
            && is_string($restore)
            && $restore !== '';

        return $this->booleanCheck(
            'database_tools',
            $ready,
            $ready
                ? 'Database dump and restore clients are available.'
                : 'Database dump and/or restore client is unavailable.',
        );
    }

    /**
     * @return array<int, array{key: string, passed: bool, message: string}>
     */
    private function productionChecks(): array
    {
        $url = (string) config('app.url');
        $appKey = (string) config('app.key');

        return [
            $this->booleanCheck(
                'production_environment',
                app()->environment('production'),
                'APP_ENV must be production.',
            ),
            $this->booleanCheck(
                'production_debug',
                ! (bool) config('app.debug'),
                'APP_DEBUG must be disabled.',
            ),
            $this->booleanCheck(
                'production_https',
                str_starts_with(strtolower($url), 'https://'),
                'APP_URL must use HTTPS.',
            ),
            $this->booleanCheck(
                'production_app_key',
                $this->validAppKey($appKey),
                'APP_KEY must be a valid 32-byte base64 key.',
            ),
            $this->booleanCheck(
                'production_installed',
                $this->installerState->isInstalled(),
                'Installer must be completed and locked.',
            ),
            $this->booleanCheck(
                'production_host_guard',
                (bool) config('security.enforce_host'),
                'Host validation must be enabled.',
            ),
            $this->booleanCheck(
                'production_csp',
                (bool) config('security.csp_enabled'),
                'Content Security Policy must be enabled.',
            ),
            $this->booleanCheck(
                'production_hsts',
                (bool) config('security.hsts_enabled'),
                'HSTS must be enabled.',
            ),
            $this->migrationCheck(),
            $this->publicStorageLinkCheck(),
        ];
    }

    /**
     * @return array{key: string, passed: bool, message: string}
     */
    private function migrationCheck(): array
    {
        try {
            if (! Schema::hasTable('migrations')) {
                return $this->booleanCheck(
                    'production_migrations',
                    false,
                    'Migration table is unavailable.',
                );
            }

            $ran = DB::table('migrations')->pluck('migration')->all();
            $files = glob(database_path('migrations/*.php')) ?: [];
            $available = array_map(
                static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
                $files,
            );
            $pending = array_values(array_diff($available, $ran));

            return $this->booleanCheck(
                'production_migrations',
                $pending === [],
                $pending === []
                    ? 'All database migrations are applied.'
                    : count($pending).' database migration(s) are pending.',
            );
        } catch (Throwable) {
            return $this->booleanCheck(
                'production_migrations',
                false,
                'Database migration state could not be verified.',
            );
        }
    }

    /**
     * @return array{key: string, passed: bool, message: string}
     */
    private function publicStorageLinkCheck(): array
    {
        $path = public_path('storage');
        $ready = is_link($path) && is_dir($path);

        return $this->booleanCheck(
            'production_storage_link',
            $ready,
            $ready
                ? 'public/storage symlink is available.'
                : 'public/storage symlink is missing or invalid.',
        );
    }

    private function validAppKey(string $key): bool
    {
        if (! str_starts_with($key, 'base64:')) {
            return false;
        }

        $decoded = base64_decode(substr($key, 7), true);

        return is_string($decoded) && strlen($decoded) === 32;
    }

    /**
     * @return array{key: string, passed: bool, message: string}
     */
    private function booleanCheck(
        string $key,
        bool $passed,
        string $message,
    ): array {
        return compact('key', 'passed', 'message');
    }
}
