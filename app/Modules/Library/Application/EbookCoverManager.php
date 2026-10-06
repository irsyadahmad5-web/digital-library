<?php

namespace App\Modules\Library\Application;

use App\Modules\Library\Application\Storage\PrivateEbookPathGuard;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EbookCoverManager
{
    public function __construct(
        private readonly PrivateEbookPathGuard $pathGuard,
    ) {}

    public function store(UploadedFile $file, ?string $oldPath = null): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs(
            'ebooks/covers',
            'cover-'.Str::uuid().'.'.$extension,
            'public',
        );

        $path = $this->pathGuard->assertCoverAllowed($path);

        if ($oldPath && $oldPath !== $path) {
            try {
                $oldPath = $this->pathGuard->assertCoverAllowed($oldPath);
                Storage::disk('public')->delete($oldPath);
            } catch (DomainException) {
                // Ignore tampered legacy paths rather than deleting outside cover scope.
            }
        }

        return $path;
    }

    public function remove(?string $path): void
    {
        if (! $path) {
            return;
        }

        try {
            $path = $this->pathGuard->assertCoverAllowed($path);
        } catch (DomainException) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    public function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            $path = $this->pathGuard->assertCoverAllowed($path);
        } catch (DomainException) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
