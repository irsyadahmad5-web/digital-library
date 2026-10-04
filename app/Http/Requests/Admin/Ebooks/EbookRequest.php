<?php

namespace App\Http\Requests\Admin\Ebooks;

use App\Modules\Settings\Application\SettingsManager;
use App\Rules\ValidIsbn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EbookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermissionTo('library.manage-ebooks');
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('slug')) {
            $this->merge([
                'slug' => Str::slug($this->string('slug')->toString()),
            ]);
        }

        if ($this->filled('isbn')) {
            $isbn = strtoupper(
                preg_replace('/[^0-9X]/i', '', $this->string('isbn')->toString()) ?? '',
            );

            $this->merge(['isbn' => $isbn]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $id = $this->route('id');
        $ebookId = is_numeric($id) ? (int) $id : null;

        $maxCoverMb = max(
            1,
            min(
                10,
                (int) app(SettingsManager::class)->get('uploads', 'max_cover_mb'),
            ),
        );

        return [
            'title' => ['required', 'string', 'max:220'],
            'subtitle' => ['nullable', 'string', 'max:220'],
            'slug' => [
                'nullable',
                'string',
                'max:240',
                Rule::unique('ebooks', 'slug')->ignore($ebookId),
            ],
            'isbn' => [
                'nullable',
                'string',
                'max:20',
                new ValidIsbn,
                Rule::unique('ebooks', 'isbn')->ignore($ebookId),
            ],
            'description' => ['nullable', 'string', 'max:20000'],
            'publication_year' => [
                'nullable',
                'integer',
                'min:1000',
                'max:'.(now()->year + 1),
            ],
            'edition' => ['nullable', 'string', 'max:80'],
            'page_count' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'publisher_id' => [
                'nullable',
                'integer',
                Rule::exists('publishers', 'id')->whereNull('deleted_at'),
            ],
            'language_id' => [
                'nullable',
                'integer',
                Rule::exists('languages', 'id')->whereNull('deleted_at'),
            ],
            'collection_id' => [
                'nullable',
                'integer',
                Rule::exists('collections', 'id')->whereNull('deleted_at'),
            ],
            'publication_status' => [
                'required',
                Rule::in(['draft', 'published', 'archived']),
            ],
            'read_enabled' => ['required', 'boolean'],
            'download_enabled' => ['required', 'boolean'],
            'cover' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.($maxCoverMb * 1024),
            ],
            'remove_cover' => ['nullable', 'boolean'],
            'authors' => ['nullable', 'array', 'max:20'],
            'authors.*' => [
                'integer',
                'distinct',
                Rule::exists('authors', 'id')->whereNull('deleted_at'),
            ],
            'categories' => ['nullable', 'array', 'max:20'],
            'categories.*' => [
                'integer',
                'distinct',
                Rule::exists('categories', 'id')->whereNull('deleted_at'),
            ],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => [
                'integer',
                'distinct',
                Rule::exists('tags', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}
