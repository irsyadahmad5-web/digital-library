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

            'sections.*.config.slides' => ['nullable', 'array', 'max:6'],
            'sections.*.config.slides.*.key' => ['required', 'string', 'uuid', 'distinct'],
            'sections.*.config.slides.*.title' => ['nullable', 'string', 'max:180'],
            'sections.*.config.slides.*.subtitle' => ['nullable', 'string', 'max:500'],
            'sections.*.config.slides.*.alt' => ['nullable', 'string', 'max:180'],
            'sections.*.config.slides.*.cta_label' => ['nullable', 'string', 'max:80'],
            'sections.*.config.slides.*.cta_href' => ['nullable', 'string', 'max:255'],
            'sections.*.config.slides.*.desktop_path' => ['nullable', 'string', 'max:255'],
            'sections.*.config.slides.*.mobile_path' => ['nullable', 'string', 'max:255'],
            'sections.*.config.slides.*.desktop_upload' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:8192',
            ],
            'sections.*.config.slides.*.mobile_upload' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:8192',
            ],
            'sections.*.config.slides.*.remove_desktop' => ['nullable', 'boolean'],
            'sections.*.config.slides.*.remove_mobile' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sections.*.config.slides.max' => 'Maksimal 6 slide pada Hero Slider.',
            'sections.*.config.slides.*.desktop_upload.image' => 'Gambar desktop slider harus berupa gambar yang valid.',
            'sections.*.config.slides.*.desktop_upload.mimes' => 'Gambar desktop slider harus JPG, PNG, atau WebP.',
            'sections.*.config.slides.*.desktop_upload.max' => 'Gambar desktop slider maksimal 8 MB.',
            'sections.*.config.slides.*.mobile_upload.image' => 'Gambar mobile slider harus berupa gambar yang valid.',
            'sections.*.config.slides.*.mobile_upload.mimes' => 'Gambar mobile slider harus JPG, PNG, atau WebP.',
            'sections.*.config.slides.*.mobile_upload.max' => 'Gambar mobile slider maksimal 8 MB.',
        ];
    }
}
