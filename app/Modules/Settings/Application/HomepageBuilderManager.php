<?php

namespace App\Modules\Settings\Application;

use App\Modules\Analytics\Application\AnalyticsReport;
use App\Modules\Library\Application\PublicLibrary\PublicLibraryCache;
use App\Modules\Library\Application\PublicLibrary\PublicLibraryCatalog;
use App\Modules\Settings\Domain\Models\HomepageSection;
use App\Modules\Settings\Support\HomepageSectionRegistry;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HomepageBuilderManager
{
    public function __construct(
        private readonly HomepageSectionRegistry $registry,
        private readonly SettingsManager $settings,
        private readonly PublicLibraryCatalog $catalog,
        private readonly AnalyticsReport $analytics,
        private readonly PublicLibraryCache $cache,
    ) {}

    public function ensureDefaults(): void
    {
        $legacy = $this->settings->group('homepage');

        $initial = [
            'hero' => [
                'enabled' => true,
                'config' => [],
            ],
            'search' => [
                'enabled' => false,
                'config' => [],
            ],
            'latest_books' => [
                'enabled' => (bool) ($legacy['show_latest'] ?? true),
                'config' => [
                    'limit' => (int) ($legacy['latest_limit'] ?? 8),
                ],
            ],
            'categories' => [
                'enabled' => (bool) ($legacy['show_categories'] ?? true),
                'config' => [],
            ],
            'collections' => [
                'enabled' => true,
                'config' => [],
            ],
            'popular_books' => [
                'enabled' => true,
                'config' => [
                    'limit' => (int) ($legacy['popular_limit'] ?? 8),
                ],
            ],
            'recommendations' => [
                'enabled' => false,
                'config' => [],
            ],
            'statistics' => [
                'enabled' => false,
                'config' => [],
            ],
        ];

        DB::transaction(function () use ($initial): void {
            $existingTypes = HomepageSection::query()
                ->pluck('type')
                ->all();

            $sortOrder = ((int) HomepageSection::query()->max('sort_order')) + 10;

            foreach ($this->registry->types() as $type) {
                if (in_array($type, $existingTypes, true)) {
                    continue;
                }

                $definition = $this->registry->section($type);
                $seed = $initial[$type] ?? [];

                HomepageSection::query()->create([
                    'type' => $type,
                    'title' => $definition['default_title'] ?? $definition['label'] ?? Str::headline($type),
                    'config_json' => [
                        ...$this->registry->defaults($type),
                        ...($seed['config'] ?? []),
                    ],
                    'is_enabled' => (bool) ($seed['enabled'] ?? $definition['default_enabled'] ?? false),
                    'sort_order' => $sortOrder,
                ]);

                $sortOrder += 10;
            }
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function adminPayload(): array
    {
        $this->ensureDefaults();

        return HomepageSection::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (HomepageSection $section): array => $this->serializeAdmin($section))
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function schemas(): array
    {
        return collect($this->registry->sections())
            ->map(fn (array $definition): array => [
                'label' => $definition['label'],
                'description' => $definition['description'],
                'provider_available' => $definition['provider_available'],
                'provider_note' => $definition['provider_note'],
                'fields' => $definition['fields'],
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    public function save(array $sections): void
    {
        $this->ensureDefaults();

        $known = HomepageSection::query()
            ->get()
            ->keyBy('id');

        if (count($sections) !== $known->count()) {
            throw ValidationException::withMessages([
                'sections' => 'Daftar section homepage tidak lengkap. Muat ulang halaman dan coba kembali.',
            ]);
        }

        DB::transaction(function () use ($sections, $known): void {
            foreach (array_values($sections) as $index => $payload) {
                $id = (int) ($payload['id'] ?? 0);
                /** @var HomepageSection|null $section */
                $section = $known->get($id);

                if (! $section) {
                    throw ValidationException::withMessages([
                        'sections' => 'Salah satu section homepage tidak valid.',
                    ]);
                }

                $type = $section->type;
                $definition = $this->registry->section($type);

                if ($definition === []) {
                    throw ValidationException::withMessages([
                        'sections' => "Tipe section {$type} tidak dikenali.",
                    ]);
                }

                $section->forceFill([
                    'title' => $this->cleanTitle(
                        $payload['title'] ?? null,
                        (string) ($definition['default_title'] ?? $definition['label']),
                    ),
                    'is_enabled' => (bool) ($payload['is_enabled'] ?? false),
                    'sort_order' => ($index + 1) * 10,
                    'config_json' => $this->normalizeConfig(
                        $type,
                        is_array($payload['config'] ?? null) ? $payload['config'] : [],
                    ),
                ])->save();
            }
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function publicPayload(): array
    {
        return $this->cache->remember(
            'homepage:public-payload',
            120,
            function (): array {
                $this->ensureDefaults();

                return HomepageSection::query()
                    ->where('is_enabled', true)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (HomepageSection $section): ?array => $this->serializePublic($section))
                    ->filter()
                    ->values()
                    ->all();
            },
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAdmin(HomepageSection $section): array
    {
        $definition = $this->registry->section($section->type);
        $config = [
            ...$this->registry->defaults($section->type),
            ...($section->config_json ?? []),
        ];

        if ($section->type === 'hero') {
            $config['slides'] = $this->serializeHeroSlides(
                is_array($config['slides'] ?? null) ? $config['slides'] : [],
                true,
            );
        }

        return [
            'id' => $section->getKey(),
            'type' => $section->type,
            'title' => $section->title,
            'is_enabled' => $section->is_enabled,
            'sort_order' => $section->sort_order,
            'config' => $config,
            'label' => $definition['label'] ?? Str::headline($section->type),
            'description' => $definition['description'] ?? '',
            'provider_available' => (bool) ($definition['provider_available'] ?? false),
            'provider_note' => $definition['provider_note'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializePublic(HomepageSection $section): ?array
    {
        $definition = $this->registry->section($section->type);

        if (! ($definition['provider_available'] ?? false)) {
            return null;
        }

        $config = [
            ...$this->registry->defaults($section->type),
            ...($section->config_json ?? []),
        ];

        if ($section->type === 'hero') {
            $config['slides'] = $this->serializeHeroSlides(
                is_array($config['slides'] ?? null) ? $config['slides'] : [],
                false,
            );
        }

        $data = match ($section->type) {
            'hero', 'search' => null,
            'latest_books' => $this->catalog->latest((int) ($config['limit'] ?? 8)),
            'popular_books' => $this->catalog->popular((int) ($config['limit'] ?? 8)),
            'recommendations' => $this->catalog->recommended((int) ($config['limit'] ?? 8)),
            'categories' => array_slice(
                $this->catalog->directoryCategories(),
                0,
                (int) ($config['limit'] ?? 12),
            ),
            'collections' => array_slice(
                $this->catalog->directoryCollections(),
                0,
                (int) ($config['limit'] ?? 8),
            ),
            'statistics' => $this->analytics->publicStatistics(),
            default => null,
        };

        if (
            in_array(
                $section->type,
                ['latest_books', 'popular_books', 'recommendations', 'categories', 'collections', 'statistics'],
                true,
            )
            && $data === []
        ) {
            return null;
        }

        return [
            'id' => $section->getKey(),
            'type' => $section->type,
            'title' => $section->title,
            'config' => $config,
            'data' => $data,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeConfig(string $type, array $input): array
    {
        $definition = $this->registry->section($type);
        $output = [];

        foreach (($definition['fields'] ?? []) as $key => $field) {
            $value = Arr::get($input, $key, $field['default'] ?? null);
            $fieldType = $field['type'] ?? 'text';
            $meta = $field['meta'] ?? [];

            $output[$key] = match ($fieldType) {
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'number' => max(
                    (int) ($meta['min'] ?? PHP_INT_MIN),
                    min((int) ($meta['max'] ?? PHP_INT_MAX), (int) $value),
                ),
                'textarea', 'text' => $this->cleanText(
                    $value,
                    (int) ($meta['max'] ?? 500),
                    (string) ($field['default'] ?? ''),
                ),
                default => $value,
            };
        }

        if ($type === 'hero') {
            $output['slides'] = $this->normalizeHeroSlides(
                is_array($input['slides'] ?? null) ? $input['slides'] : [],
            );
        }

        return $output;
    }

    /**
     * @param  array<int, mixed>  $slides
     * @return array<int, array<string, mixed>>
     */
    private function normalizeHeroSlides(array $slides): array
    {
        return collect(array_slice($slides, 0, 6))
            ->filter(fn ($slide): bool => is_array($slide))
            ->map(function (array $slide): array {
                $href = trim(is_scalar($slide['cta_href'] ?? null)
                    ? (string) $slide['cta_href']
                    : '/library');

                if ($href === '' || ! str_starts_with($href, '/') || str_starts_with($href, '//')) {
                    $href = '/library';
                }

                $desktopPath = $this->managedMediaPath($slide['desktop_path'] ?? null);
                $mobilePath = $this->managedMediaPath($slide['mobile_path'] ?? null);

                return [
                    'key' => Str::isUuid((string) ($slide['key'] ?? ''))
                        ? (string) $slide['key']
                        : (string) Str::uuid(),
                    'title' => $this->cleanText($slide['title'] ?? '', 180, ''),
                    'subtitle' => $this->cleanText($slide['subtitle'] ?? '', 500, ''),
                    'alt' => $this->cleanText($slide['alt'] ?? '', 180, ''),
                    'cta_label' => $this->cleanText($slide['cta_label'] ?? '', 80, ''),
                    'cta_href' => $href,
                    'desktop_path' => $desktopPath,
                    'mobile_path' => $mobilePath,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $slides
     * @return array<int, array<string, mixed>>
     */
    private function serializeHeroSlides(array $slides, bool $includePaths): array
    {
        return collect($slides)
            ->filter(fn ($slide): bool => is_array($slide))
            ->map(function (array $slide) use ($includePaths): array {
                $desktopPath = $this->managedMediaPath($slide['desktop_path'] ?? null);
                $mobilePath = $this->managedMediaPath($slide['mobile_path'] ?? null);

                $payload = [
                    'key' => (string) ($slide['key'] ?? ''),
                    'title' => (string) ($slide['title'] ?? ''),
                    'subtitle' => (string) ($slide['subtitle'] ?? ''),
                    'alt' => (string) ($slide['alt'] ?? ''),
                    'cta_label' => (string) ($slide['cta_label'] ?? ''),
                    'cta_href' => (string) ($slide['cta_href'] ?? '/library'),
                    'desktop_url' => $desktopPath ? Storage::disk('public')->url($desktopPath) : null,
                    'mobile_url' => $mobilePath ? Storage::disk('public')->url($mobilePath) : null,
                ];

                if ($includePaths) {
                    $payload['desktop_path'] = $desktopPath;
                    $payload['mobile_path'] = $mobilePath;
                }

                return $payload;
            })
            ->filter(fn (array $slide): bool => $includePaths
                || $slide['desktop_url'] !== null
                || $slide['mobile_url'] !== null)
            ->values()
            ->all();
    }

    private function managedMediaPath(mixed $path): ?string
    {
        if (! is_string($path) || ! str_starts_with($path, 'homepage/slides/')) {
            return null;
        }

        return $path;
    }

    private function cleanTitle(mixed $value, string $fallback): string
    {
        return $this->cleanText($value, 255, $fallback);
    }

    private function cleanText(mixed $value, int $max, string $fallback): string
    {
        $value = trim(is_scalar($value) ? (string) $value : '');

        if ($value === '' && $fallback !== '') {
            $value = $fallback;
        }

        return Str::limit($value, $max, '');
    }
}
