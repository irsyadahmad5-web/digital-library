<?php

namespace App\Http\Requests\Admin\MasterData;

use App\Modules\Library\Support\MasterDataRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class MasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermissionTo('library.manage-master-data');
    }

    protected function prepareForValidation(): void
    {
        $entity = (string) $this->route('entity');

        if ($this->filled('slug')) {
            $this->merge([
                'slug' => Str::slug($this->string('slug')->toString()),
            ]);
        }

        if ($entity === 'languages' && $this->filled('code')) {
            $this->merge([
                'code' => Str::lower($this->string('code')->toString()),
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $id = $this->route('id');

        return app(MasterDataRegistry::class)->rules(
            (string) $this->route('entity'),
            is_numeric($id) ? (int) $id : null,
        );
    }
}
