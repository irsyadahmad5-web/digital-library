<?php

namespace App\Modules\Library\Application;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EbookCoverManager
{
    public function store(UploadedFile $file, ?string $oldPath = null): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs(
            'ebooks/covers',
            'cover-'.Str::uuid().'.'.$extension,
            'public',
        );

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return $path;
    }

    public function remove(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    public function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
