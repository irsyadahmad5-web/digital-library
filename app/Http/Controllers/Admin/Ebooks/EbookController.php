<?php

namespace App\Http\Controllers\Admin\Ebooks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Ebooks\BulkEbookRequest;
use App\Http\Requests\Admin\Ebooks\EbookRequest;
use App\Modules\Audit\Application\AuditLogger;
use App\Modules\Library\Application\EbookCoverManager;
use App\Modules\Library\Application\Pdf\PdfToolchain;
use App\Modules\Library\Application\PublicLibrary\PublicLibraryCache;
use App\Modules\Library\Application\Storage\EbookFileManager;
use App\Modules\Library\Application\Storage\UploadPolicy;
use App\Modules\Library\Domain\Models\Author;
use App\Modules\Library\Domain\Models\Category;
use App\Modules\Library\Domain\Models\Collection;
use App\Modules\Library\Domain\Models\Ebook;
use App\Modules\Library\Domain\Models\Language;
use App\Modules\Library\Domain\Models\Publisher;
use App\Modules\Library\Domain\Models\Tag;
use App\Modules\Settings\Application\SettingsManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class EbookController extends Controller
{
    public function __construct(
        private readonly EbookCoverManager $covers,
        private readonly SettingsManager $settings,
        private readonly EbookFileManager $files,
        private readonly UploadPolicy $uploadPolicy,
        private readonly PdfToolchain $pdfToolchain,
        private readonly PublicLibraryCache $publicCache,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,draft,published,archived'],
            'access' => ['nullable', 'in:all,readable,downloadable,locked'],
            'category_id' => ['nullable', 'integer'],
            'author_id' => ['nullable', 'integer'],
            'language_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $status = (string) ($filters['status'] ?? 'all');
        $access = (string) ($filters['access'] ?? 'all');

        $query = Ebook::query()
            ->with([
                'authors:id,name',
                'publisher:id,name',
                'language:id,name,code',
                'collection:id,name',
                'file',
            ])
            ->latest('updated_at')
            ->latest('id');

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('subtitle', 'like', '%'.$search.'%')
                    ->orWhere('isbn', 'like', '%'.$search.'%')
                    ->orWhereHas(
                        'authors',
                        fn (Builder $authorQuery) => $authorQuery->where('name', 'like', '%'.$search.'%'),
                    );
            });
        }

        if ($status !== 'all') {
            $query->where('publication_status', $status);
        }

        match ($access) {
            'readable' => $query->where('read_enabled', true),
            'downloadable' => $query->where('download_enabled', true),
            'locked' => $query
                ->where('read_enabled', false)
                ->where('download_enabled', false),
            default => null,
        };

        if (! empty($filters['category_id'])) {
            $query->whereHas(
                'categories',
                fn (Builder $categoryQuery) => $categoryQuery->whereKey((int) $filters['category_id']),
            );
        }

        if (! empty($filters['author_id'])) {
            $query->whereHas(
                'authors',
                fn (Builder $authorQuery) => $authorQuery->whereKey((int) $filters['author_id']),
            );
        }

        if (! empty($filters['language_id'])) {
            $query->where('language_id', (int) $filters['language_id']);
        }

        $ebooks = $query
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString()
            ->through(fn (Ebook $ebook): array => $this->serializeForIndex($ebook));

        return Inertia::render('Admin/Ebooks/Index', [
            'ebooks' => $ebooks,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'access' => $access,
                'category_id' => isset($filters['category_id']) ? (int) $filters['category_id'] : null,
                'author_id' => isset($filters['author_id']) ? (int) $filters['author_id'] : null,
                'language_id' => isset($filters['language_id']) ? (int) $filters['language_id'] : null,
                'per_page' => (int) ($filters['per_page'] ?? 25),
            ],
            'filterOptions' => [
                'categories' => $this->categoryOptions(),
                'authors' => $this->simpleOptions(Author::class),
                'languages' => $this->languageOptions(),
            ],
        ]);
    }

    public function create(): Response
    {
        return $this->formResponse();
    }

    public function store(
        EbookRequest $request,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validated();
        $payload = $this->normalizePayload($validated);
        $payload['slug'] = $payload['slug']
            ?: $this->uniqueSlug((string) $payload['title']);
        $payload['created_by'] = $request->user()?->getKey();
        $payload['updated_by'] = $request->user()?->getKey();
        $payload['published_at'] = $payload['publication_status'] === 'published'
            ? now()
            : null;

        $coverPath = null;

        if ($request->hasFile('cover')) {
            $coverPath = $this->covers->store($request->file('cover'));
            $payload['cover_path'] = $coverPath;
        }

        try {
            $ebook = DB::transaction(function () use ($payload, $validated, $request, $audit): Ebook {
                $ebook = Ebook::query()->create($payload);
                $this->syncRelations($ebook, $validated);

                $audit->log(
                    'library.ebook.created',
                    actor: $request->user(),
                    subjectType: 'ebook',
                    subjectId: $ebook->getKey(),
                    metadata: [
                        'title' => $ebook->title,
                        'status' => $ebook->publication_status,
                    ],
                    request: $request,
                );

                return $ebook;
            });
        } catch (Throwable $exception) {
            if ($coverPath) {
                $this->covers->remove($coverPath);
            }

            throw $exception;
        }

        return redirect()
            ->route('admin.ebooks.edit', ['id' => $ebook->getKey()])
            ->with('status', 'Ebook berhasil ditambahkan.');
    }

    public function edit(int $id): Response
    {
        $ebook = Ebook::query()
            ->with([
                'authors:id',
                'categories:id',
                'tags:id',
                'file',
            ])
            ->findOrFail($id);

        return $this->formResponse($ebook);
    }

    public function update(
        EbookRequest $request,
        int $id,
        AuditLogger $audit,
    ): RedirectResponse {
        $ebook = Ebook::query()->findOrFail($id);
        $validated = $request->validated();
        $payload = $this->normalizePayload($validated);

        if (! $payload['slug']) {
            $payload['slug'] = $ebook->slug;
        }

        $payload['updated_by'] = $request->user()?->getKey();
        $payload['published_at'] = $this->publishedAtForUpdate(
            $ebook,
            (string) $payload['publication_status'],
        );

        $oldCover = $ebook->cover_path;
        $newCover = null;
        $removeOldCover = false;

        if ($request->boolean('remove_cover')) {
            $payload['cover_path'] = null;
            $removeOldCover = (bool) $oldCover;
        }

        if ($request->hasFile('cover')) {
            $newCover = $this->covers->store($request->file('cover'));
            $payload['cover_path'] = $newCover;
            $removeOldCover = (bool) $oldCover;
        }

        try {
            DB::transaction(function () use ($ebook, $payload, $validated, $request, $audit): void {
                $ebook->fill($payload);
                $ebook->save();

                $this->syncRelations($ebook, $validated);

                $audit->log(
                    'library.ebook.updated',
                    actor: $request->user(),
                    subjectType: 'ebook',
                    subjectId: $ebook->getKey(),
                    metadata: [
                        'title' => $ebook->title,
                        'status' => $ebook->publication_status,
                    ],
                    request: $request,
                );
            });
        } catch (Throwable $exception) {
            if ($newCover) {
                $this->covers->remove($newCover);
            }

            throw $exception;
        }

        if ($removeOldCover && $oldCover && $oldCover !== $newCover) {
            $this->covers->remove($oldCover);
        }

        return back()->with('status', 'Ebook berhasil diperbarui.');
    }

    public function destroy(
        Request $request,
        int $id,
        AuditLogger $audit,
    ): RedirectResponse {
        $ebook = Ebook::query()->findOrFail($id);

        DB::transaction(function () use ($ebook, $request, $audit): void {
            $ebook->delete();

            $audit->log(
                'library.ebook.deleted',
                actor: $request->user(),
                subjectType: 'ebook',
                subjectId: $ebook->getKey(),
                metadata: ['title' => $ebook->title],
                request: $request,
            );
        });

        return redirect()
            ->route('admin.ebooks.index')
            ->with('status', 'Ebook berhasil dihapus.');
    }

    public function bulk(
        BulkEbookRequest $request,
        AuditLogger $audit,
    ): RedirectResponse {
        $ids = array_values(array_unique($request->validated('ids')));
        $action = (string) $request->validated('action');
        $ebooks = Ebook::query()->whereIn('id', $ids)->get();

        if ($ebooks->isEmpty()) {
            return back()->withErrors([
                'ids' => 'Tidak ada ebook valid yang dipilih.',
            ]);
        }

        DB::transaction(function () use ($ebooks, $action, $request, $audit): void {
            foreach ($ebooks as $ebook) {
                $this->applyBulkAction($ebook, $action);
            }

            $audit->log(
                'library.ebook.bulk',
                actor: $request->user(),
                subjectType: 'ebook',
                metadata: [
                    'action' => $action,
                    'count' => $ebooks->count(),
                    'ids' => $ebooks->modelKeys(),
                ],
                request: $request,
            );
        });

        return back()->with(
            'status',
            "Bulk action {$action} berhasil diterapkan pada {$ebooks->count()} ebook.",
        );
    }

    private function formResponse(?Ebook $ebook = null): Response
    {
        $selectedAuthors = $ebook?->authors->modelKeys() ?? [];
        $selectedCategories = $ebook?->categories->modelKeys() ?? [];
        $selectedTags = $ebook?->tags->modelKeys() ?? [];

        return Inertia::render('Admin/Ebooks/Form', [
            'ebook' => $ebook ? $this->serializeForForm($ebook) : null,
            'maxCoverMb' => max(
                1,
                min(10, (int) $this->settings->get('uploads', 'max_cover_mb')),
            ),
            'fileSource' => $ebook ? $this->files->serialize($ebook->file) : null,
            'processingConfig' => $this->processingConfig(),
            'uploadConfig' => [
                'max_pdf_mb' => (int) $this->settings->get('uploads', 'max_pdf_mb'),
                'max_pdf_bytes' => $this->uploadPolicy->maxPdfBytes(),
                'configured_chunk_mb' => (int) $this->settings->get('uploads', 'chunk_size_mb'),
                'effective_chunk_bytes' => $this->uploadPolicy->effectiveChunkBytes(),
                'checksum_enabled' => $this->uploadPolicy->checksumEnabled(),
                'preferred_source' => (string) $this->settings->get('storage', 'preferred_source'),
                'verify_external_urls' => (bool) $this->settings->get('storage', 'verify_external_urls'),
                'https_only_external' => (bool) $this->settings->get('storage', 'https_only_external'),
            ],
            'options' => [
                'authors' => $this->simpleOptions(Author::class, $selectedAuthors),
                'categories' => $this->categoryOptions($selectedCategories),
                'publishers' => $this->simpleOptions(
                    Publisher::class,
                    array_filter([$ebook?->publisher_id]),
                ),
                'languages' => $this->languageOptions(
                    array_filter([$ebook?->language_id]),
                ),
                'collections' => $this->simpleOptions(
                    Collection::class,
                    array_filter([$ebook?->collection_id]),
                ),
                'tags' => $this->simpleOptions(Tag::class, $selectedTags),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeForIndex(Ebook $ebook): array
    {
        return [
            'id' => $ebook->getKey(),
            'title' => $ebook->title,
            'subtitle' => $ebook->subtitle,
            'slug' => $ebook->slug,
            'isbn' => $ebook->isbn,
            'cover_url' => $this->covers->url($ebook->cover_path)
                ?? $this->files->previewUrl($ebook->file),
            'authors' => $ebook->authors->pluck('name')->values()->all(),
            'publisher' => $ebook->publisher?->name,
            'language' => $ebook->language?->name,
            'collection' => $ebook->collection?->name,
            'publication_status' => $ebook->publication_status,
            'read_enabled' => $ebook->read_enabled,
            'download_enabled' => $ebook->download_enabled,
            'file_source_type' => $ebook->file?->source_type,
            'file_verification_status' => $ebook->file?->verification_status,
            'file_processing_status' => $ebook->file?->processing_status,
            'file_page_count' => $ebook->file?->page_count,
            'file_size_bytes' => $ebook->file?->size_bytes,
            'published_at' => $ebook->published_at?->toIso8601String(),
            'updated_at' => $ebook->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeForForm(Ebook $ebook): array
    {
        return [
            'id' => $ebook->getKey(),
            'title' => $ebook->title,
            'subtitle' => $ebook->subtitle,
            'slug' => $ebook->slug,
            'isbn' => $ebook->isbn,
            'description' => $ebook->description,
            'publication_year' => $ebook->publication_year,
            'edition' => $ebook->edition,
            'page_count' => $ebook->page_count,
            'cover_url' => $this->covers->url($ebook->cover_path),
            'publisher_id' => $ebook->publisher_id,
            'language_id' => $ebook->language_id,
            'collection_id' => $ebook->collection_id,
            'publication_status' => $ebook->publication_status,
            'read_enabled' => $ebook->read_enabled,
            'download_enabled' => $ebook->download_enabled,
            'published_at' => $ebook->published_at?->toIso8601String(),
            'authors' => $ebook->authors->modelKeys(),
            'categories' => $ebook->categories->modelKeys(),
            'tags' => $ebook->tags->modelKeys(),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizePayload(array $validated): array
    {
        $payload = Arr::only($validated, [
            'title',
            'subtitle',
            'slug',
            'isbn',
            'description',
            'publication_year',
            'edition',
            'page_count',
            'publisher_id',
            'language_id',
            'collection_id',
            'publication_status',
            'read_enabled',
            'download_enabled',
        ]);

        foreach ([
            'subtitle',
            'slug',
            'isbn',
            'description',
            'edition',
            'publisher_id',
            'language_id',
            'collection_id',
        ] as $key) {
            if (($payload[$key] ?? null) === '') {
                $payload[$key] = null;
            }
        }

        $payload['title'] = trim((string) $payload['title']);
        $payload['slug'] = $payload['slug']
            ? Str::slug((string) $payload['slug'])
            : null;
        $payload['read_enabled'] = filter_var(
            $payload['read_enabled'] ?? false,
            FILTER_VALIDATE_BOOL,
        );
        $payload['download_enabled'] = filter_var(
            $payload['download_enabled'] ?? false,
            FILTER_VALIDATE_BOOL,
        );

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncRelations(Ebook $ebook, array $validated): void
    {
        $authorIds = array_values($validated['authors'] ?? []);
        $authors = [];

        foreach ($authorIds as $index => $authorId) {
            $authors[(int) $authorId] = ['sort_order' => $index];
        }

        $ebook->authors()->sync($authors);
        $ebook->categories()->sync(array_values($validated['categories'] ?? []));
        $ebook->tags()->sync(array_values($validated['tags'] ?? []));

        $this->publicCache->flush();
    }

    private function publishedAtForUpdate(Ebook $ebook, string $status): mixed
    {
        if ($status === 'draft') {
            return null;
        }

        if ($status === 'published') {
            return $ebook->publication_status === 'published' && $ebook->published_at
                ? $ebook->published_at
                : now();
        }

        return $ebook->published_at;
    }

    private function applyBulkAction(Ebook $ebook, string $action): void
    {
        match ($action) {
            'publish' => $ebook->forceFill([
                'publication_status' => 'published',
                'published_at' => $ebook->published_at ?? now(),
            ])->save(),
            'draft' => $ebook->forceFill([
                'publication_status' => 'draft',
                'published_at' => null,
            ])->save(),
            'archive' => $ebook->forceFill([
                'publication_status' => 'archived',
            ])->save(),
            'enable_read' => $ebook->forceFill(['read_enabled' => true])->save(),
            'disable_read' => $ebook->forceFill(['read_enabled' => false])->save(),
            'enable_download' => $ebook->forceFill(['download_enabled' => true])->save(),
            'disable_download' => $ebook->forceFill(['download_enabled' => false])->save(),
            'delete' => $ebook->delete(),
            default => null,
        };
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'ebook';
        $candidate = $base;
        $suffix = 2;

        while (Ebook::withTrashed()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @return array<string, bool>
     */
    private function processingConfig(): array
    {
        $availability = $this->pdfToolchain->availability();
        $pdfInfo = is_string($availability['pdfinfo'] ?? false);
        $pdfToCairo = is_string($availability['pdftocairo'] ?? false);

        return [
            'pdfinfo_available' => $pdfInfo,
            'pdftocairo_available' => $pdfToCairo,
            'available' => $pdfInfo && $pdfToCairo,
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, int|string>  $selected
     * @return array<int, array<string, mixed>>
     */
    private function simpleOptions(string $modelClass, array $selected = []): array
    {
        return $modelClass::query()
            ->where(function (Builder $query) use ($selected): void {
                $query->where('is_active', true);

                if ($selected !== []) {
                    $query->orWhereIn('id', $selected);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'is_active'])
            ->map(fn (Model $model): array => [
                'value' => $model->getKey(),
                'label' => $model->getAttribute('name'),
                'active' => (bool) $model->getAttribute('is_active'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int|string>  $selected
     * @return array<int, array<string, mixed>>
     */
    private function categoryOptions(array $selected = []): array
    {
        return Category::query()
            ->with('parent:id,name')
            ->where(function (Builder $query) use ($selected): void {
                $query->where('is_active', true);

                if ($selected !== []) {
                    $query->orWhereIn('id', $selected);
                }
            })
            ->orderByRaw('parent_id IS NOT NULL')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'is_active'])
            ->map(fn (Category $category): array => [
                'value' => $category->getKey(),
                'label' => $category->parent
                    ? $category->parent->name.' — '.$category->name
                    : $category->name,
                'active' => $category->is_active,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int|string>  $selected
     * @return array<int, array<string, mixed>>
     */
    private function languageOptions(array $selected = []): array
    {
        return Language::query()
            ->where(function (Builder $query) use ($selected): void {
                $query->where('is_active', true);

                if ($selected !== []) {
                    $query->orWhereIn('id', $selected);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'is_active'])
            ->map(fn (Language $language): array => [
                'value' => $language->getKey(),
                'label' => $language->name.' ('.$language->code.')',
                'active' => $language->is_active,
            ])
            ->values()
            ->all();
    }
}
