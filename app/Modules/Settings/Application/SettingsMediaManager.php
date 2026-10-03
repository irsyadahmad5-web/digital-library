<?php

namespace App\Modules\Settings\Application;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SettingsMediaManager
{
    public function storeBranding(UploadedFile $file, string $kind, ?string $oldPath = null): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = $kind.'-'.Str::uuid().'.'.$extension;
        $path = $file->storeAs('branding', $filename, 'public');

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
}
