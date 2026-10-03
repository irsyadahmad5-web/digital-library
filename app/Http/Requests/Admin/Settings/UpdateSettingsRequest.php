<?php

namespace App\Http\Requests\Admin\Settings;

use App\Modules\Settings\Support\SettingsRegistry;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermissionTo('admin.manage-settings');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return app(SettingsRegistry::class)->rules(
            (string) $this->route('group'),
        );
    }
}
