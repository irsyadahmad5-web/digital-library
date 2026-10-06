<?php

namespace App\Modules\Operations\Application;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

class BackupManager
{
    public function __construct(
        private readonly DatabaseBackup $databaseBackup,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function create(): array
    {
        if (! (bool) config('operations.backup.enabled', true)) {
            throw new RuntimeException(
                'Backup otomatis dinonaktifkan oleh konfigurasi.',
            );
        }

        $root = $this->root();
        $this->ensureDirectory($root, 0700);

        $name = now()->format('Ymd-His').'-'.bin2hex(random_bytes(4));
        $partial = $root.'/.partial-'.$name;
        $final = $root.'/'.$name;

        $this->ensureDirectory($partial, 0700);

        try {
            $database = $this->databaseBackup->dump($partial);

            $sources = (array) config(
                'operations.backup.sources',
                [],
            );

            foreach ($sources as $relative => $source) {
                $relative = $this->safeRelativePath((string) $relative);
                $source = (string) $source;
                if (! is_dir($source)) {
                    continue;
                }

                $this->copyTree(
                    $source,
                    $partial.'/'.$relative,
                );
            }

            $files = $this->manifestFiles($partial);
            $totalBytes = array_sum(array_column($files, 'size'));

            $manifest = [
                'format_version' => 1,
                'name' => $name,
                'created_at' => now()->toIso8601String(),
                'database' => $database,
                'files' => $files,
                'file_count' => count($files),
                'total_bytes' => $totalBytes,
            ];

            $manifestPath = $partial.'/manifest.json';
            $encoded = json_encode(
                $manifest,
                JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR,
            );

            if (
                file_put_contents(
                    $manifestPath,
                    $encoded.PHP_EOL,
                    LOCK_EX,
                ) === false
            ) {
                throw new RuntimeException(
                    'Manifest backup gagal ditulis.',
                );
            }

            @chmod($manifestPath, 0600);

            if (! @rename($partial, $final)) {
                throw new RuntimeException(
                    'Snapshot backup gagal dipublikasikan secara atomik.',
                );
            }

            return [
                ...$manifest,
                'path' => $final,
            ];
        } catch (\Throwable $exception) {
            $this->removeTree($partial);

            throw $exception;
        }
    }

    /**
     * @return array{
     *     valid: bool,
     *     name: string,
     *     checked_files: int,
     *     total_bytes: int,
     *     errors: array<int, string>
     * }
     */
    public function verify(string $name): array
    {
        $directory = $this->directory($name);
        $manifestPath = $directory.'/manifest.json';

        if (! is_file($manifestPath)) {
            return [
                'valid' => false,
                'name' => $name,
                'checked_files' => 0,
                'total_bytes' => 0,
                'errors' => ['manifest.json tidak ditemukan.'],
            ];
        }

        $manifest = json_decode(
            (string) file_get_contents($manifestPath),
            true,
        );

        if (
            ! is_array($manifest)
            || ($manifest['format_version'] ?? null) !== 1
            || ! is_array($manifest['files'] ?? null)
        ) {
            return [
                'valid' => false,
                'name' => $name,
                'checked_files' => 0,
                'total_bytes' => 0,
                'errors' => ['Format manifest backup tidak valid.'],
            ];
        }

        $errors = [];
        $checked = 0;
        $bytes = 0;
        $expectedPaths = [];

        foreach ($manifest['files'] as $entry) {
            if (
                ! is_array($entry)
                || ! is_string($entry['path'] ?? null)
                || ! is_string($entry['sha256'] ?? null)
                || ! is_int($entry['size'] ?? null)
            ) {
                $errors[] = 'Entry manifest tidak valid.';

                continue;
            }

            $relative = $this->safeRelativePath($entry['path']);
            $expectedPaths[] = $relative;
            $path = $directory.'/'.$relative;

            if (! is_file($path) || is_link($path)) {
                $errors[] = "File hilang: {$relative}";

                continue;
            }

            $size = filesize($path);

            if ($size === false || $size !== $entry['size']) {
                $errors[] = "Ukuran file berubah: {$relative}";

                continue;
            }

            $hash = hash_file('sha256', $path);

            if (
                ! is_string($hash)
                || ! hash_equals($entry['sha256'], $hash)
            ) {
                $errors[] = "Checksum tidak cocok: {$relative}";

                continue;
            }

            $checked++;
            $bytes += $entry['size'];
        }

        $actualPaths = $this->actualBackupFiles($directory);
        sort($expectedPaths);
        sort($actualPaths);

        foreach (array_diff($actualPaths, $expectedPaths) as $unexpected) {
            $errors[] = "File tidak terdaftar di manifest: {$unexpected}";
        }

        foreach (array_diff($expectedPaths, $actualPaths) as $missing) {
            $errors[] = "File manifest tidak tersedia: {$missing}";
        }

        return [
            'valid' => $errors === [],
            'name' => $name,
            'checked_files' => $checked,
            'total_bytes' => $bytes,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{
     *     deleted: array<int, string>,
     *     kept: array<int, string>
     * }
     */
    public function prune(): array
    {
        $backups = $this->list();
        $keepCount = (int) config(
            'operations.backup.retention_count',
            14,
        );
        $retentionDays = (int) config(
            'operations.backup.retention_days',
            30,
        );
        $cutoff = now()->subDays($retentionDays)->getTimestamp();

        $deleted = [];
        $kept = [];

        foreach ($backups as $index => $backup) {
            $created = strtotime(
                (string) ($backup['created_at'] ?? ''),
            );
            $tooMany = $index >= $keepCount;
            $tooOld = $created !== false && $created < $cutoff;

            if ($index > 0 && ($tooMany || $tooOld)) {
                $this->removeTree(
                    $this->directory($backup['name']),
                );
                $deleted[] = $backup['name'];

                continue;
            }

            $kept[] = $backup['name'];
        }

        return compact('deleted', 'kept');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        $root = $this->root();

        if (! is_dir($root)) {
            return [];
        }

        $items = [];

        foreach (scandir($root) ?: [] as $name) {
            if (
                $name === '.'
                || $name === '..'
                || ! preg_match(
                    '/^\d{8}-\d{6}-[a-f0-9]{8}$/',
                    $name,
                )
            ) {
                continue;
            }

            $path = $root.'/'.$name;

            if (! is_dir($path) || is_link($path)) {
                continue;
            }

            $manifest = $this->readManifest($path);

            if ($manifest === null) {
                continue;
            }

            $items[] = [
                'name' => $name,
                'created_at' => $manifest['created_at'] ?? null,
                'file_count' => (int) ($manifest['file_count'] ?? 0),
                'total_bytes' => (int) ($manifest['total_bytes'] ?? 0),
                'database_driver' => $manifest['database']['driver'] ?? null,
            ];
        }

        usort(
            $items,
            static fn (array $left, array $right): int => strcmp($right['name'], $left['name']),
        );

        return $items;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latest(): ?array
    {
        return $this->list()[0] ?? null;
    }

    private function root(): string
    {
        $root = rtrim(
            (string) config(
                'operations.backup.path',
                storage_path('app/backups'),
            ),
            DIRECTORY_SEPARATOR,
        );

        if ($root === '' || $root === DIRECTORY_SEPARATOR) {
            throw new RuntimeException(
                'Path backup tidak aman.',
            );
        }

        $normalizedRoot = $this->normalizedPath($root);
        $normalizedPublic = rtrim(
            $this->normalizedPath(public_path()),
            '/',
        );

        if (
            $normalizedRoot === $normalizedPublic
            || str_starts_with(
                $normalizedRoot,
                $normalizedPublic.'/',
            )
        ) {
            throw new RuntimeException(
                'Path backup tidak boleh berada di dalam public web root.',
            );
        }

        return $root;
    }

    private function directory(string $name): string
    {
        if (! preg_match(
            '/^\d{8}-\d{6}-[a-f0-9]{8}$/',
            $name,
        )) {
            throw new RuntimeException(
                'Nama backup tidak valid.',
            );
        }

        return $this->root().'/'.$name;
    }

    private function safeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));

        if (
            $path === ''
            || str_starts_with($path, '/')
            || str_contains($path, "\0")
        ) {
            throw new RuntimeException(
                'Path manifest backup tidak valid.',
            );
        }

        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if (
                $segment === ''
                || $segment === '.'
                || $segment === '..'
            ) {
                throw new RuntimeException(
                    'Path manifest backup tidak aman.',
                );
            }
        }

        return implode('/', $segments);
    }

    private function copyTree(
        string $source,
        string $target,
    ): void {
        $this->ensureDirectory($target, 0700);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $source,
                RecursiveDirectoryIterator::SKIP_DOTS,
            ),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            if (! $item instanceof SplFileInfo) {
                continue;
            }

            if ($item->isLink()) {
                continue;
            }

            $relative = substr(
                $item->getPathname(),
                strlen(rtrim($source, DIRECTORY_SEPARATOR)) + 1,
            );
            $destination = $target.'/'.$relative;

            if ($item->isDir()) {
                $this->ensureDirectory(
                    $destination,
                    0700,
                );

                continue;
            }

            $this->ensureDirectory(
                dirname($destination),
                0700,
            );

            if (! @copy($item->getPathname(), $destination)) {
                throw new RuntimeException(
                    "File backup gagal disalin: {$relative}",
                );
            }

            @chmod($destination, 0600);
        }
    }

    /**
     * @return array<int, array{path: string, size: int, sha256: string}>
     */
    private function manifestFiles(string $directory): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory,
                RecursiveDirectoryIterator::SKIP_DOTS,
            ),
        );

        foreach ($iterator as $item) {
            if (
                ! $item instanceof SplFileInfo
                || ! $item->isFile()
                || $item->isLink()
                || $item->getFilename() === 'manifest.json'
                || $item->getFilename() === '.database-client.cnf'
            ) {
                continue;
            }

            $relative = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                substr(
                    $item->getPathname(),
                    strlen(rtrim($directory, DIRECTORY_SEPARATOR)) + 1,
                ),
            );
            $size = $item->getSize();
            $hash = hash_file(
                'sha256',
                $item->getPathname(),
            );

            if (! is_string($hash)) {
                throw new RuntimeException(
                    "Checksum backup gagal: {$relative}",
                );
            }

            $files[] = [
                'path' => $relative,
                'size' => $size,
                'sha256' => $hash,
            ];
        }

        usort(
            $files,
            static fn (array $left, array $right): int => strcmp($left['path'], $right['path']),
        );

        return $files;
    }

    /**
     * @return array<int, string>
     */
    private function actualBackupFiles(string $directory): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory,
                RecursiveDirectoryIterator::SKIP_DOTS,
            ),
        );

        foreach ($iterator as $item) {
            if (
                ! $item instanceof SplFileInfo
                || ! $item->isFile()
                || $item->isLink()
                || $item->getFilename() === 'manifest.json'
            ) {
                continue;
            }

            $files[] = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                substr(
                    $item->getPathname(),
                    strlen(rtrim($directory, DIRECTORY_SEPARATOR)) + 1,
                ),
            );
        }

        sort($files);

        return $files;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readManifest(string $directory): ?array
    {
        $path = $directory.'/manifest.json';

        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode(
            (string) file_get_contents($path),
            true,
        );

        return is_array($decoded) ? $decoded : null;
    }

    private function ensureDirectory(
        string $path,
        int $mode,
    ): void {
        if (is_dir($path)) {
            return;
        }

        if (
            ! @mkdir($path, $mode, true)
            && ! is_dir($path)
        ) {
            throw new RuntimeException(
                "Direktori tidak dapat dibuat: {$path}",
            );
        }
    }

    private function normalizedPath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $segments = [];
        $prefix = str_starts_with($path, '/') ? '/' : '';

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return $prefix.implode('/', $segments);
    }

    private function removeTree(string $path): void
    {
        if (! is_dir($path) || is_link($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $path,
                RecursiveDirectoryIterator::SKIP_DOTS,
            ),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if (! $item instanceof SplFileInfo) {
                continue;
            }

            if ($item->isDir() && ! $item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($path);
    }
}
