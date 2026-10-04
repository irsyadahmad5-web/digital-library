<?php

namespace Database\Seeders;

use App\Modules\Settings\Application\HomepageBuilderManager;
use App\Modules\Settings\Application\SettingsManager;
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

        app(SettingsManager::class)->flush();
        app(HomepageBuilderManager::class)->ensureDefaults();
    }
}
