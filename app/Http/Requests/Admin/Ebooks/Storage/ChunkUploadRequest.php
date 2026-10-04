<?php

namespace App\Http\Requests\Admin\Ebooks\Storage;

use Illuminate\Foundation\Http\FormRequest;

class ChunkUploadRequest extends FormRequest
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
            'chunk' => ['required', 'file', 'max:51200'],
            'chunk_sha256' => ['nullable', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
        ];
    }
}
