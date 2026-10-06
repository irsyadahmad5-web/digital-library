<?php

namespace App\Modules\Library\Application\Storage;

use DomainException;

class PrivateEbookPathGuard
{
    public function assertAllowed(int|string $ebookId, string $path): string
    {
        $path = trim($path);

        if (
            $path === ''
            || str_contains($path, "\0")
            || str_contains($path, '\\')
            || str_starts_with($path, '/')
        ) {
            throw new DomainException('Path private ebook tidak valid.');
        }

        $currentPrefix = 'ebooks/'.(string) $ebookId.'/';
        $legacyPrefix = 'ebooks/';

        if (str_starts_with($path, $currentPrefix)) {
            $filename = substr($path, strlen($currentPrefix));
        } elseif (str_starts_with($path, $legacyPrefix)) {
            $filename = substr($path, strlen($legacyPrefix));

            if (str_contains($filename, '/')) {
                throw new DomainException(
                    'Path private ebook berada di luar direktori ebook.',
                );
            }
        } else {
            throw new DomainException(
                'Path private ebook berada di luar direktori ebook.',
            );
        }

        if (
            $filename === ''
            || str_contains($filename, '/')
            || $filename === '.'
            || $filename === '..'
            || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,220}\.pdf$/i', $filename)
        ) {
            throw new DomainException('Nama file private ebook tidak valid.');
        }

        return $path;
    }

    public function assertCoverAllowed(string $path): string
    {
        $path = trim($path);
        $prefix = 'ebooks/covers/';

        if (
            $path === ''
            || str_contains($path, "\0")
            || str_contains($path, '\\')
            || str_starts_with($path, '/')
            || ! str_starts_with($path, $prefix)
        ) {
            throw new DomainException('Path cover ebook tidak valid.');
        }

        $filename = substr($path, strlen($prefix));

        if (
            $filename === ''
            || str_contains($filename, '/')
            || ! preg_match(
                '/^[A-Za-z0-9][A-Za-z0-9._-]{0,220}\.(?:jpe?g|png|webp)$/i',
                $filename,
            )
        ) {
            throw new DomainException('Nama file cover ebook tidak valid.');
        }

        return $path;
    }

    public function assertPreviewAllowed(int|string $ebookId, string $path): string
    {
        $path = trim($path);
        $prefix = 'ebooks/generated-previews/'.(string) $ebookId.'/';

        if (
            $path === ''
            || str_contains($path, "\0")
            || str_contains($path, '\\')
            || str_starts_with($path, '/')
            || ! str_starts_with($path, $prefix)
        ) {
            throw new DomainException('Path preview ebook tidak valid.');
        }

        $filename = substr($path, strlen($prefix));

        if (
            $filename === ''
            || str_contains($filename, '/')
            || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,220}\.jpe?g$/i', $filename)
        ) {
            throw new DomainException('Nama file preview ebook tidak valid.');
        }

        return $path;
    }
}
