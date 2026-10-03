<?php

namespace Database\Seeders;

use App\Modules\Settings\Application\SettingsManager;
use App\Modules\Settings\Domain\Models\HomepageSection;
use App\Modules\Settings\Domain\Models\Setting;
use App\Modules\Settings\Support\SettingsRegistry;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $registry = app(SettingsRegistry::class);

        foreach ($registry->groups() as $groupKey => $group) {
            foreach ($group['fields'] as $key => $definition) {
                Setting::query()->firstOrCreate(
                    [
                        'group' => $groupKey,
                        'key' => $key,
                    ],
                    [
                        'value_json' => json_encode($definition['default'], JSON_THROW_ON_ERROR),
                        'value_type' => $definition['type'],
                        'is_public' => $definition['public'],
                        'is_encrypted' => $definition['encrypted'],
                    ],
                );
            }
        }

        if (HomepageSection::query()->doesntExist()) {
            HomepageSection::query()->insert([
                [
                    'type' => 'hero',
                    'title' => 'Hero',
                    'config_json' => json_encode([], JSON_THROW_ON_ERROR),
                    'is_enabled' => true,
                    'sort_order' => 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'type' => 'latest_books',
                    'title' => 'Buku Terbaru',
                    'config_json' => json_encode([], JSON_THROW_ON_ERROR),
                    'is_enabled' => true,
                    'sort_order' => 20,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'type' => 'popular_books',
                    'title' => 'Buku Populer',
                    'config_json' => json_encode([], JSON_THROW_ON_ERROR),
                    'is_enabled' => true,
                    'sort_order' => 30,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'type' => 'categories',
                    'title' => 'Kategori',
                    'config_json' => json_encode([], JSON_THROW_ON_ERROR),
                    'is_enabled' => true,
                    'sort_order' => 40,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        app(SettingsManager::class)->flush();
    }
}
