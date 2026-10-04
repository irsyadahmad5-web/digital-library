<?php

namespace App\Http\Requests\Admin\Ebooks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkEbookRequest extends FormRequest
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
            'action' => [
                'required',
                Rule::in([
                    'publish',
                    'draft',
                    'archive',
                    'enable_read',
                    'disable_read',
                    'enable_download',
                    'disable_download',
                    'delete',
                ]),
            ],
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ];
    }
}
