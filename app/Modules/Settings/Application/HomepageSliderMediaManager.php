<?php

namespace App\Modules\Settings\Application;

use App\Modules\Settings\Domain\Models\HomepageSection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HomepageSliderMediaManager
{
    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array{
     *   sections: array<int, array<string, mixed>>,
     *   created_paths: array<int, string>,
     *   obsolete_paths: array<int, string>
     * }
     */
    public function prepare(array $sections): array
    {
        $hero = HomepageSection::query()
            ->where('type', 'hero')
            ->first();

        if (! $hero) {
            return [
                'sections' => $sections,
                'created_paths' => [],
                'obsolete_paths' => [],
            ];
        }

        $existingSlides = collect($hero->config_json['slides'] ?? [])
            ->filter(fn ($slide): bool => is_array($slide) && is_string($slide['key'] ?? null))
            ->keyBy('key');

        $created = [];
        $obsolete = [];

        foreach ($sections as &$section) {
            if ((int) ($section['id'] ?? 0) !== (int) $hero->getKey()) {
                continue;
            }

            $incomingSlides = is_array($section['config']['slides'] ?? null)
                ? array_slice($section['config']['slides'], 0, 6)
                : [];

            $preparedSlides = [];
            $seenKeys = [];

            foreach ($incomingSlides as $slide) {
                if (! is_array($slide)) {
                    continue;
                }

                $key = is_string($slide['key'] ?? null) && Str::isUuid($slide['key'])
                    ? $slide['key']
                    : (string) Str::uuid();

                if (isset($seenKeys[$key])) {
                    $key = (string) Str::uuid();
                }

                $seenKeys[$key] = true;
                $old = $existingSlides->get($key, []);

                $desktopPath = is_array($old) && is_string($old['desktop_path'] ?? null)
                    ? $old['desktop_path']
                    : null;
                $mobilePath = is_array($old) && is_string($old['mobile_path'] ?? null)
                    ? $old['mobile_path']
                    : null;

                if (($slide['desktop_upload'] ?? null) instanceof UploadedFile) {
                    $newPath = $this->store(
                        $slide['desktop_upload'],
                        $key,
                        'desktop',
                    );
                    $created[] = $newPath;

                    if ($desktopPath && $desktopPath !== $newPath) {
                        $obsolete[] = $desktopPath;
                    }

                    $desktopPath = $newPath;
                } elseif ((bool) ($slide['remove_desktop'] ?? false)) {
                    if ($desktopPath) {
                        $obsolete[] = $desktopPath;
                    }
                    $desktopPath = null;
                }

                if (($slide['mobile_upload'] ?? null) instanceof UploadedFile) {
                    $newPath = $this->store(
                        $slide['mobile_upload'],
                        $key,
                        'mobile',
                    );
                    $created[] = $newPath;

                    if ($mobilePath && $mobilePath !== $newPath) {
                        $obsolete[] = $mobilePath;
                    }

                    $mobilePath = $newPath;
                } elseif ((bool) ($slide['remove_mobile'] ?? false)) {
                    if ($mobilePath) {
                        $obsolete[] = $mobilePath;
                    }
                    $mobilePath = null;
                }

                $preparedSlides[] = [
                    'key' => $key,
                    'title' => $slide['title'] ?? '',
                    'subtitle' => $slide['subtitle'] ?? '',
                    'alt' => $slide['alt'] ?? '',
                    'cta_label' => $slide['cta_label'] ?? '',
                    'cta_href' => $slide['cta_href'] ?? '/library',
                    'desktop_path' => $desktopPath,
                    'mobile_path' => $mobilePath,
                ];
            }

            $incomingKeys = collect($preparedSlides)
                ->pluck('key')
                ->all();

            foreach ($existingSlides as $key => $old) {
                if (in_array($key, $incomingKeys, true)) {
                    continue;
                }

                foreach (['desktop_path', 'mobile_path'] as $pathKey) {
                    if (is_string($old[$pathKey] ?? null) && $old[$pathKey] !== '') {
                        $obsolete[] = $old[$pathKey];
                    }
                }
            }

            $section['config']['slides'] = $preparedSlides;
        }
        unset($section);

        return [
            'sections' => $sections,
            'created_paths' => array_values(array_unique($created)),
            'obsolete_paths' => array_values(array_unique($obsolete)),
        ];
    }

    /** @param array<int, string> $paths */
    public function commit(array $paths): void
    {
        foreach (array_unique($paths) as $path) {
            if ($this->isManagedPath($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    /** @param array<int, string> $paths */
    public function rollback(array $paths): void
    {
        foreach (array_unique($paths) as $path) {
            if ($this->isManagedPath($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function store(UploadedFile $file, string $key, string $variant): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = $key.'-'.$variant.'-'.Str::uuid().'.'.$extension;

        return $file->storeAs('homepage/slides', $filename, 'public');
    }

    private function isManagedPath(string $path): bool
    {
        return str_starts_with($path, 'homepage/slides/');
    }
}
