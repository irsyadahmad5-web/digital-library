<?php

namespace App\Http\Requests\Admin\Ebooks\Storage;

use Illuminate\Foundation\Http\FormRequest;

class ExternalSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermissionTo('library.manage-ebooks');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'external_url' => ['required', 'url:http,https', 'max:2048'],
        ];
    }
}
