<?php

namespace App\Http\Requests\Admin\Settings;

use App\Modules\Settings\Support\HomepageSectionRegistry;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHomepageBuilderRequest extends FormRequest
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
        $sectionCount = count(app(HomepageSectionRegistry::class)->types());

        return [
            'sections' => ['required', 'array', 'size:'.$sectionCount],
            'sections.*.id' => ['required', 'integer', 'distinct', 'exists:homepage_sections,id'],
            'sections.*.title' => ['nullable', 'string', 'max:255'],
            'sections.*.is_enabled' => ['required', 'boolean'],
            'sections.*.config' => ['nullable', 'array'],
        ];
    }
}
