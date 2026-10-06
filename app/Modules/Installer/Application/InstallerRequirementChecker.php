<?php

namespace App\Modules\Installer\Application;

use App\Modules\Library\Application\Pdf\PdfToolchain;

class InstallerRequirementChecker
{
    public function __construct(
        private readonly PdfToolchain $pdfToolchain,
    ) {}

    /**
     * @return array{
     *     passed: bool,
     *     checks: array<int, array{
     *         key: string,
     *         label: string,
     *         ok: bool,
     *         required: bool,
     *         detail: string
     *     }>
     * }
     */
    public function check(): array
    {
        $checks = [];

        $minimumPhp = (string) config(
            'installer.minimum_php',
            '8.3.0',
        );

        $checks[] = $this->row(
            'php',
            'PHP >= '.$minimumPhp,
            version_compare(PHP_VERSION, $minimumPhp, '>='),
            true,
            'Terdeteksi PHP '.PHP_VERSION,
        );

        foreach ((array) config('installer.required_extensions', []) as $extension) {
            $extension = (string) $extension;

            $checks[] = $this->row(
                'ext-'.$extension,
                'Ekstensi '.$extension,
                extension_loaded($extension),
                true,
                extension_loaded($extension)
                    ? 'Aktif'
                    : 'Belum aktif',
            );
        }

        $disabledFunctions = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ini_get('disable_functions')),
        )));

        foreach ((array) config('installer.required_functions', []) as $function) {
            $function = (string) $function;
            $available = function_exists($function)
                && ! in_array($function, $disabledFunctions, true);

            $checks[] = $this->row(
                'fn-'.$function,
                'Fungsi '.$function,
                $available,
                true,
                $available ? 'Tersedia' : 'Tidak tersedia / dinonaktifkan',
            );
        }

        foreach ($this->writablePaths() as $key => $path) {
            $checks[] = $this->row(
                'write-'.$key,
                'Writable: '.$key,
                $this->isWritableTarget($path),
                true,
                $path,
            );
        }

        $checks[] = $this->row(
            'vendor',
            'Composer dependencies',
            is_file(base_path('vendor/autoload.php')),
            true,
            base_path('vendor/autoload.php'),
        );

        $checks[] = $this->row(
            'frontend',
            'Frontend production build',
            is_file(public_path('build/manifest.json')),
            true,
            public_path('build/manifest.json'),
        );

        $availability = $this->pdfToolchain->availability();

        foreach ([
            'pdfinfo' => 'Poppler pdfinfo',
            'pdftocairo' => 'Poppler pdftocairo',
        ] as $key => $label) {
            $binary = $availability[$key] ?? false;

            $checks[] = $this->row(
                'binary-'.$key,
                $label,
                is_string($binary) && $binary !== '',
                true,
                is_string($binary) && $binary !== ''
                    ? $binary
                    : 'Binary tidak ditemukan di PATH',
            );
        }

        $uploadLimit = $this->iniBytes(
            (string) ini_get('upload_max_filesize'),
        );
        $postLimit = $this->iniBytes(
            (string) ini_get('post_max_size'),
        );
        $recommended = 12 * 1024 * 1024;

        $checks[] = $this->row(
            'upload-limit',
            'PHP upload/post limit >= 12 MB',
            $uploadLimit >= $recommended
                && $postLimit >= $recommended,
            false,
            'upload_max_filesize='
                .(string) ini_get('upload_max_filesize')
                .', post_max_size='
                .(string) ini_get('post_max_size')
                .' (chunk upload default 10 MB)',
        );

        $checks[] = $this->row(
            'https',
            'HTTPS aktif',
            request()->isSecure(),
            false,
            request()->isSecure()
                ? 'Koneksi saat ini HTTPS'
                : 'Disarankan mengaktifkan HTTPS sebelum production',
        );

        return [
            'passed' => collect($checks)
                ->where('required', true)
                ->every(fn (array $check): bool => $check['ok']),
            'checks' => $checks,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function writablePaths(): array
    {
        return [
            '.env' => (string) config(
                'installer.env_path',
                base_path('.env'),
            ),
            'storage' => storage_path(),
            'storage/app' => storage_path('app'),
            'storage/framework' => storage_path('framework'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];
    }

    private function isWritableTarget(string $path): bool
    {
        if (file_exists($path)) {
            return is_writable($path);
        }

        $parent = dirname($path);

        while (! is_dir($parent) && dirname($parent) !== $parent) {
            $parent = dirname($parent);
        }

        return is_dir($parent) && is_writable($parent);
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     ok: bool,
     *     required: bool,
     *     detail: string
     * }
     */
    private function row(
        string $key,
        string $label,
        bool $ok,
        bool $required,
        string $detail,
    ): array {
        return compact(
            'key',
            'label',
            'ok',
            'required',
            'detail',
        );
    }

    private function iniBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) substr($value, 0, -1);

        return (int) match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => (float) $value,
        };
    }
}
