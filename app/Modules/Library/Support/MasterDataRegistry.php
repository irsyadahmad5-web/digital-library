<?php

namespace App\Modules\Library\Support;

use App\Modules\Library\Domain\Models\Author;
use App\Modules\Library\Domain\Models\Category;
use App\Modules\Library\Domain\Models\Collection;
use App\Modules\Library\Domain\Models\Language;
use App\Modules\Library\Domain\Models\Publisher;
use App\Modules\Library\Domain\Models\Tag;
use Illuminate\Validation\Rule;

class MasterDataRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function entities(): array
    {
        return [
            'categories' => [
                'label' => 'Kategori',
                'singular' => 'Kategori',
                'description' => 'Kelola kategori dan subkategori koleksi ebook.',
                'model' => Category::class,
                'search' => ['name', 'slug', 'description'],
                'order' => ['sort_order', 'name'],
                'columns' => [
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'parent_name', 'label' => 'Induk'],
                    ['key' => 'slug', 'label' => 'Slug'],
                    ['key' => 'is_active', 'label' => 'Status'],
                ],
                'fields' => [
                    'parent_id' => $this->select('Kategori induk', null, false, 'Kosongkan untuk kategori utama.', ['nullable', 'integer']),
                    'name' => $this->field('Nama', 'text', '', true, ['required', 'string', 'max:120']),
                    'slug' => $this->field('Slug', 'text', '', false, ['nullable', 'string', 'max:160'], 'Boleh dikosongkan agar dibuat otomatis.'),
                    'description' => $this->field('Deskripsi', 'textarea', '', false, ['nullable', 'string', 'max:2000']),
                    'sort_order' => $this->number('Urutan', 0, false, 0, 9999),
                    'is_active' => $this->boolean('Aktif', true),
                ],
            ],
            'authors' => [
                'label' => 'Penulis',
                'singular' => 'Penulis',
                'description' => 'Kelola identitas penulis ebook.',
                'model' => Author::class,
                'search' => ['name', 'slug', 'bio'],
                'order' => ['name'],
                'columns' => [
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'slug', 'label' => 'Slug'],
                    ['key' => 'website', 'label' => 'Website'],
                    ['key' => 'is_active', 'label' => 'Status'],
                ],
                'fields' => [
                    'name' => $this->field('Nama', 'text', '', true, ['required', 'string', 'max:160']),
                    'slug' => $this->field('Slug', 'text', '', false, ['nullable', 'string', 'max:190'], 'Boleh dikosongkan agar dibuat otomatis.'),
                    'bio' => $this->field('Biografi singkat', 'textarea', '', false, ['nullable', 'string', 'max:4000']),
                    'website' => $this->field('Website', 'url', '', false, ['nullable', 'url:http,https', 'max:255']),
                    'is_active' => $this->boolean('Aktif', true),
                ],
            ],
            'publishers' => [
                'label' => 'Penerbit',
                'singular' => 'Penerbit',
                'description' => 'Kelola data penerbit ebook.',
                'model' => Publisher::class,
                'search' => ['name', 'slug', 'description'],
                'order' => ['name'],
                'columns' => [
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'slug', 'label' => 'Slug'],
                    ['key' => 'website', 'label' => 'Website'],
                    ['key' => 'is_active', 'label' => 'Status'],
                ],
                'fields' => [
                    'name' => $this->field('Nama', 'text', '', true, ['required', 'string', 'max:160']),
                    'slug' => $this->field('Slug', 'text', '', false, ['nullable', 'string', 'max:190'], 'Boleh dikosongkan agar dibuat otomatis.'),
                    'description' => $this->field('Deskripsi', 'textarea', '', false, ['nullable', 'string', 'max:3000']),
                    'website' => $this->field('Website', 'url', '', false, ['nullable', 'url:http,https', 'max:255']),
                    'is_active' => $this->boolean('Aktif', true),
                ],
            ],
            'tags' => [
                'label' => 'Tag',
                'singular' => 'Tag',
                'description' => 'Kelola tag untuk pencarian dan pengelompokan ebook.',
                'model' => Tag::class,
                'search' => ['name', 'slug'],
                'order' => ['name'],
                'columns' => [
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'slug', 'label' => 'Slug'],
                    ['key' => 'is_active', 'label' => 'Status'],
                ],
                'fields' => [
                    'name' => $this->field('Nama', 'text', '', true, ['required', 'string', 'max:120']),
                    'slug' => $this->field('Slug', 'text', '', false, ['nullable', 'string', 'max:160'], 'Boleh dikosongkan agar dibuat otomatis.'),
                    'is_active' => $this->boolean('Aktif', true),
                ],
            ],
            'collections' => [
                'label' => 'Koleksi',
                'singular' => 'Koleksi',
                'description' => 'Kelola kelompok koleksi seperti Referensi, Umum, atau Pilihan.',
                'model' => Collection::class,
                'search' => ['name', 'slug', 'description'],
                'order' => ['name'],
                'columns' => [
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'slug', 'label' => 'Slug'],
                    ['key' => 'is_active', 'label' => 'Status'],
                ],
                'fields' => [
                    'name' => $this->field('Nama', 'text', '', true, ['required', 'string', 'max:160']),
                    'slug' => $this->field('Slug', 'text', '', false, ['nullable', 'string', 'max:190'], 'Boleh dikosongkan agar dibuat otomatis.'),
                    'description' => $this->field('Deskripsi', 'textarea', '', false, ['nullable', 'string', 'max:3000']),
                    'is_active' => $this->boolean('Aktif', true),
                ],
            ],
            'languages' => [
                'label' => 'Bahasa',
                'singular' => 'Bahasa',
                'description' => 'Kelola bahasa yang tersedia pada koleksi ebook.',
                'model' => Language::class,
                'search' => ['code', 'name', 'native_name'],
                'order' => ['sort_order', 'name'],
                'columns' => [
                    ['key' => 'code', 'label' => 'Kode'],
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'native_name', 'label' => 'Nama Lokal'],
                    ['key' => 'is_active', 'label' => 'Status'],
                ],
                'fields' => [
                    'code' => $this->field('Kode bahasa', 'text', '', true, ['required', 'string', 'max:16', 'alpha_dash']),
                    'name' => $this->field('Nama', 'text', '', true, ['required', 'string', 'max:120']),
                    'native_name' => $this->field('Nama lokal', 'text', '', false, ['nullable', 'string', 'max:120']),
                    'sort_order' => $this->number('Urutan', 0, false, 0, 9999),
                    'is_active' => $this->boolean('Aktif', true),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function entity(string $entity): array
    {
        return $this->entities()[$entity] ?? [];
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->entities());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(string $entity, ?int $id = null): array
    {
        $definition = $this->entity($entity);

        if ($definition === []) {
            return [];
        }

        $rules = collect($definition['fields'])
            ->mapWithKeys(fn (array $field, string $key) => [$key => $field['rules']])
            ->all();

        if (isset($rules['slug'])) {
            $rules['slug'][] = Rule::unique($this->table($entity), 'slug')->ignore($id);
        }

        if ($entity === 'languages') {
            $rules['code'][] = Rule::unique('languages', 'code')->ignore($id);
        }

        if ($entity === 'categories') {
            $parentRules = [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')
                    ->whereNull('deleted_at')
                    ->whereNull('parent_id'),
            ];

            if ($id !== null) {
                $parentRules[] = Rule::notIn([$id]);
            }

            $rules['parent_id'] = $parentRules;
        }

        return $rules;
    }

    public function table(string $entity): string
    {
        return match ($entity) {
            'categories' => 'categories',
            'authors' => 'authors',
            'publishers' => 'publishers',
            'tags' => 'tags',
            'collections' => 'collections',
            'languages' => 'languages',
            default => '',
        };
    }

    /**
     * @param  array<int, mixed>  $rules
     * @return array<string, mixed>
     */
    private function field(
        string $label,
        string $type,
        mixed $default,
        bool $required,
        array $rules,
        string $description = '',
    ): array {
        return [
            'label' => $label,
            'type' => $type,
            'default' => $default,
            'required' => $required,
            'description' => $description,
            'rules' => $rules,
            'options' => [],
            'meta' => [],
        ];
    }

    /**
     * @param  array<int, mixed>  $rules
     * @return array<string, mixed>
     */
    private function select(
        string $label,
        mixed $default,
        bool $required,
        string $description,
        array $rules,
    ): array {
        return $this->field($label, 'select', $default, $required, $rules, $description);
    }

    /**
     * @return array<string, mixed>
     */
    private function boolean(string $label, bool $default): array
    {
        return $this->field($label, 'boolean', $default, true, ['required', 'boolean']);
    }

    /**
     * @return array<string, mixed>
     */
    private function number(
        string $label,
        int $default,
        bool $required,
        int $min,
        int $max,
    ): array {
        $field = $this->field(
            $label,
            'number',
            $default,
            $required,
            ['required', 'integer', "min:{$min}", "max:{$max}"],
        );

        $field['meta'] = ['min' => $min, 'max' => $max];

        return $field;
    }
}
