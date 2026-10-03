<?php

namespace App\Modules\Settings\Application;

use App\Modules\Settings\Domain\Models\Setting;
use App\Modules\Settings\Support\SettingsRegistry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Throwable;

class SettingsManager
{
    private const CACHE_KEY = 'digital-library:settings:v1';

    public function __construct(
        private readonly SettingsRegistry $registry,
    ) {}

    public function get(string $group, string $key): mixed
    {
        return $this->all()[$group][$key]
            ?? $this->registry->field($group, $key)['default']
            ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        return $this->all()[$group] ?? $this->registry->defaults($group);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => $this->load(),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function public(): array
    {
        $all = $this->all();
        $result = [];

        foreach ($this->registry->groups() as $groupKey => $group) {
            foreach ($group['fields'] as $key => $definition) {
                if (! ($definition['public'] ?? false)) {
                    continue;
                }

                $result[$groupKey][$key] = $all[$groupKey][$key]
                    ?? $definition['default'];
            }
        }

        $result['general']['logo_url'] = $this->mediaUrl(
            (string) ($result['general']['logo_path'] ?? ''),
        );
        $result['general']['favicon_url'] = $this->mediaUrl(
            (string) ($result['general']['favicon_path'] ?? ''),
        );

        return $result;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function updateGroup(string $group, array $values, ?int $userId): void
    {
        $definitions = $this->registry->group($group)['fields'] ?? [];

        DB::transaction(function () use ($group, $values, $userId, $definitions): void {
            foreach ($values as $key => $value) {
                $definition = $definitions[$key] ?? null;

                if (! is_array($definition)) {
                    continue;
                }

                $normalized = $this->normalize($definition, $value);

                Setting::query()->updateOrCreate(
                    [
                        'group' => $group,
                        'key' => $key,
                    ],
                    [
                        'value_json' => $this->encode(
                            $normalized,
                            (bool) ($definition['encrypted'] ?? false),
                        ),
                        'value_type' => (string) ($definition['type'] ?? 'string'),
                        'is_public' => (bool) ($definition['public'] ?? false),
                        'is_encrypted' => (bool) ($definition['encrypted'] ?? false),
                        'updated_by' => $userId,
                    ],
                );
            }
        });

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function mediaUrl(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function load(): array
    {
        $values = $this->registry->defaults();

        try {
            $rows = Setting::query()->get();
        } catch (QueryException) {
            return $values;
        }

        foreach ($rows as $row) {
            $definition = $this->registry->field($row->group, $row->key);

            if ($definition === []) {
                continue;
            }

            try {
                $values[$row->group][$row->key] = $this->decode(
                    $row->value_json,
                    $row->is_encrypted,
                );
            } catch (Throwable) {
                $values[$row->group][$row->key] = $definition['default'] ?? null;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function normalize(array $definition, mixed $value): mixed
    {
        return match ($definition['type'] ?? 'text') {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL),
            'number' => (int) $value,
            default => $value === null ? '' : (string) $value,
        };
    }

    private function encode(mixed $value, bool $encrypted): string
    {
        $json = json_encode($value, JSON_THROW_ON_ERROR);

        return $encrypted ? Crypt::encryptString($json) : $json;
    }

    /**
     * @throws JsonException
     */
    private function decode(?string $value, bool $encrypted): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $json = $encrypted ? Crypt::decryptString($value) : $value;

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }
}
