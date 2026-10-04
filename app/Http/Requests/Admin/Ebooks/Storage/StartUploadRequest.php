<?php

namespace App\Http\Requests\Admin\Ebooks\Storage;

use App\Modules\Library\Application\Storage\UploadPolicy;
use Illuminate\Foundation\Http\FormRequest;

class StartUploadRequest extends FormRequest
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
            'file_name' => ['required', 'string', 'max:255'],
            'size_bytes' => [
                'required',
                'integer',
                'min:5',
                'max:'.app(UploadPolicy::class)->maxPdfBytes(),
            ],
            'sha256' => ['nullable', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
        ];
    }
}
