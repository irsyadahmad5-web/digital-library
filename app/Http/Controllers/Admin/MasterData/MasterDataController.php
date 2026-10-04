<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterData\BulkMasterDataRequest;
use App\Http\Requests\Admin\MasterData\MasterDataRequest;
use App\Modules\Audit\Application\AuditLogger;
use App\Modules\Library\Domain\Models\Category;
use App\Modules\Library\Support\MasterDataRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MasterDataController extends Controller
{
    public function __construct(
        private readonly MasterDataRegistry $registry,
    ) {}

    public function redirect(): RedirectResponse
    {
        return redirect()->route('admin.master-data.index', [
            'entity' => 'categories',
        ]);
    }

    public function index(Request $request, string $entity): Response
    {
        $definition = $this->definition($entity);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,active,inactive'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
        ]);

        $modelClass = $definition['model'];
        $query = $modelClass::query();

        if ($entity === 'categories') {
            $query->with('parent:id,name');
        }

        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($definition, $search): void {
                foreach ($definition['search'] as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $builder->{$method}($column, 'like', '%'.$search.'%');
                }
            });
        }

        $status = (string) ($filters['status'] ?? 'all');

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        foreach ($definition['order'] as $column) {
            $query->orderBy($column);
        }

        /** @var LengthAwarePaginator $items */
        $items = $query
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();

        $items->through(
            fn (Model $model): array => $this->serialize($entity, $definition, $model),
        );

        $schema = [
            'label' => $definition['label'],
            'singular' => $definition['singular'],
            'description' => $definition['description'],
            'columns' => $definition['columns'],
            'fields' => collect($definition['fields'])
                ->map(fn (array $field): array => Arr::except($field, ['rules']))
                ->all(),
        ];

        return Inertia::render('Admin/MasterData/Index', [
            'entities' => collect($this->registry->entities())
                ->map(fn (array $item, string $key): array => [
                    'key' => $key,
                    'label' => $item['label'],
                    'description' => $item['description'],
                ])
                ->values()
                ->all(),
            'activeEntity' => $entity,
            'schema' => $schema,
            'items' => $items,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'per_page' => (int) ($filters['per_page'] ?? 25),
            ],
            'options' => $this->options($entity),
        ]);
    }

    public function store(
        MasterDataRequest $request,
        string $entity,
        AuditLogger $audit,
    ): RedirectResponse {
        $definition = $this->definition($entity);
        $modelClass = $definition['model'];
        $payload = $this->normalizePayload($entity, $request->validated());

        if (array_key_exists('slug', $definition['fields']) && empty($payload['slug'])) {
            $payload['slug'] = $this->uniqueSlug(
                $modelClass,
                (string) ($payload['name'] ?? 'item'),
            );
        }

        $model = DB::transaction(function () use ($modelClass, $payload, $entity, $request, $audit): Model {
            /** @var Model $created */
            $created = $modelClass::query()->create($payload);

            $audit->log(
                'library.master_data.created',
                actor: $request->user(),
                subjectType: $entity,
                subjectId: $created->getKey(),
                metadata: ['name' => $created->getAttribute('name')],
                request: $request,
            );

            return $created;
        });

        return back()->with(
            'status',
            "{$definition['singular']} {$model->getAttribute('name')} berhasil ditambahkan.",
        );
    }

    public function update(
        MasterDataRequest $request,
        string $entity,
        int $id,
        AuditLogger $audit,
    ): RedirectResponse {
        $definition = $this->definition($entity);
        $modelClass = $definition['model'];

        /** @var Model $model */
        $model = $modelClass::query()->findOrFail($id);
        $payload = $this->normalizePayload($entity, $request->validated());

        if (array_key_exists('slug', $definition['fields']) && empty($payload['slug'])) {
            $payload['slug'] = (string) $model->getAttribute('slug');
        }

        DB::transaction(function () use ($model, $payload, $entity, $request, $audit): void {
            $model->fill($payload);
            $model->save();

            $audit->log(
                'library.master_data.updated',
                actor: $request->user(),
                subjectType: $entity,
                subjectId: $model->getKey(),
                metadata: ['name' => $model->getAttribute('name')],
                request: $request,
            );
        });

        return back()->with(
            'status',
            "{$definition['singular']} berhasil diperbarui.",
        );
    }

    public function destroy(
        Request $request,
        string $entity,
        int $id,
        AuditLogger $audit,
    ): RedirectResponse {
        $definition = $this->definition($entity);
        $modelClass = $definition['model'];

        /** @var Model $model */
        $model = $modelClass::query()->findOrFail($id);

        $this->ensureDeletable($entity, $model);

        DB::transaction(function () use ($model, $entity, $request, $audit): void {
            $name = $model->getAttribute('name');
            $model->delete();

            $audit->log(
                'library.master_data.deleted',
                actor: $request->user(),
                subjectType: $entity,
                subjectId: $model->getKey(),
                metadata: ['name' => $name],
                request: $request,
            );
        });

        return back()->with(
            'status',
            "{$definition['singular']} berhasil dihapus.",
        );
    }

    public function bulk(
        BulkMasterDataRequest $request,
        string $entity,
        AuditLogger $audit,
    ): RedirectResponse {
        $definition = $this->definition($entity);
        $modelClass = $definition['model'];
        $ids = array_values(array_unique($request->validated('ids')));
        $action = (string) $request->validated('action');

        $models = $modelClass::query()
            ->whereIn('id', $ids)
            ->get();

        if ($models->isEmpty()) {
            throw ValidationException::withMessages([
                'ids' => 'Tidak ada data valid yang dipilih.',
            ]);
        }

        if ($action === 'delete') {
            foreach ($models as $model) {
                $this->ensureDeletable($entity, $model);
            }
        }

        DB::transaction(function () use ($models, $action, $entity, $request, $audit): void {
            if ($action === 'delete') {
                $models->each(fn (Model $model) => $model->delete());
            } else {
                $active = $action === 'activate';

                foreach ($models as $model) {
                    $model->forceFill(['is_active' => $active])->save();
                }
            }

            $audit->log(
                'library.master_data.bulk',
                actor: $request->user(),
                subjectType: $entity,
                metadata: [
                    'action' => $action,
                    'count' => $models->count(),
                    'ids' => $models->modelKeys(),
                ],
                request: $request,
            );
        });

        return back()->with(
            'status',
            "Bulk action {$action} berhasil diterapkan pada {$models->count()} data.",
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(string $entity): array
    {
        $definition = $this->registry->entity($entity);

        abort_if($definition === [], 404);

        return $definition;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function serialize(string $entity, array $definition, Model $model): array
    {
        $data = [
            'id' => $model->getKey(),
            'updated_at' => $model->getAttribute('updated_at')?->toIso8601String(),
        ];

        foreach (array_keys($definition['fields']) as $key) {
            $data[$key] = $model->getAttribute($key);
        }

        if ($entity === 'categories') {
            $data['parent_name'] = $model->getRelation('parent')?->name;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function options(string $entity): array
    {
        if ($entity !== 'categories') {
            return [];
        }

        return [
            'parent_id' => Category::query()
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Category $category): array => [
                    'value' => $category->getKey(),
                    'label' => $category->name,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(string $entity, array $payload): array
    {
        $definition = $this->definition($entity);

        foreach ($definition['fields'] as $key => $field) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $payload[$key] = match ($field['type']) {
                'boolean' => filter_var($payload[$key], FILTER_VALIDATE_BOOL),
                'number' => (int) $payload[$key],
                'select' => $payload[$key] === '' ? null : $payload[$key],
                default => is_string($payload[$key]) ? trim($payload[$key]) : $payload[$key],
            };
        }

        return $payload;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function uniqueSlug(string $modelClass, string $name): string
    {
        $base = Str::slug($name) ?: 'item';
        $candidate = $base;
        $suffix = 2;

        while ($modelClass::withTrashed()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function ensureDeletable(string $entity, Model $model): void
    {
        if ($entity !== 'categories') {
            return;
        }

        /** @var Category $model */
        if ($model->children()->exists()) {
            throw ValidationException::withMessages([
                'ids' => 'Kategori yang masih memiliki subkategori tidak dapat dihapus.',
            ]);
        }
    }
}
