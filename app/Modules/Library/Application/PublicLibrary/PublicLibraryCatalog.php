<?php

namespace App\Modules\Library\Application\PublicLibrary;

use App\Modules\Library\Application\EbookCoverManager;
use App\Modules\Library\Application\Storage\EbookFileManager;
use App\Modules\Library\Domain\Models\Author;
use App\Modules\Library\Domain\Models\Category;
use App\Modules\Library\Domain\Models\Collection;
use App\Modules\Library\Domain\Models\Ebook;
use App\Modules\Library\Domain\Models\Language;
use App\Modules\Library\Domain\Models\Publisher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PublicLibraryCatalog
{
    public function __construct(
        private readonly EbookCoverManager $covers,
        private readonly EbookFileManager $files,
    ) {}

    public function query(array $filters = []): Builder
    {
        $query = Ebook::query()
            ->publiclyVisible()
            ->with([
                'authors:id,name,slug,is_active',
                'categories:id,parent_id,name,slug,is_active',
                'publisher:id,name,slug,is_active',
                'language:id,code,name,native_name,is_active',
                'collection:id,name,slug,is_active',
                'tags:id,name,slug,is_active',
                'file',
            ]);

        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';

                $builder
                    ->where('title', 'like', $like)
                    ->orWhere('subtitle', 'like', $like)
                    ->orWhere('isbn', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('authors', fn (Builder $relation): Builder => $relation
                        ->where('is_active', true)
                        ->where('name', 'like', $like))
                    ->orWhereHas('categories', fn (Builder $relation): Builder => $relation
                        ->where('is_active', true)
                        ->where('name', 'like', $like))
                    ->orWhereHas('publisher', fn (Builder $relation): Builder => $relation
                        ->where('is_active', true)
                        ->where('name', 'like', $like))
                    ->orWhereHas('tags', fn (Builder $relation): Builder => $relation
                        ->where('is_active', true)
                        ->where('name', 'like', $like));
            });
        }

        if ($slug = $this->cleanSlug($filters['category'] ?? null)) {
            $query->whereHas(
                'categories',
                fn (Builder $relation): Builder => $relation
                    ->where('is_active', true)
                    ->where('slug', $slug),
            );
        }

        if ($slug = $this->cleanSlug($filters['author'] ?? null)) {
            $query->whereHas(
                'authors',
                fn (Builder $relation): Builder => $relation
                    ->where('is_active', true)
                    ->where('slug', $slug),
            );
        }

        if ($slug = $this->cleanSlug($filters['publisher'] ?? null)) {
            $query->whereHas(
                'publisher',
                fn (Builder $relation): Builder => $relation
                    ->where('is_active', true)
                    ->where('slug', $slug),
            );
        }

        if ($slug = $this->cleanSlug($filters['collection'] ?? null)) {
            $query->whereHas(
                'collection',
                fn (Builder $relation): Builder => $relation
                    ->where('is_active', true)
                    ->where('slug', $slug),
            );
        }

        if ($language = $this->cleanSlug($filters['language'] ?? null)) {
            $query->whereHas(
                'language',
                fn (Builder $relation): Builder => $relation
                    ->where('is_active', true)
                    ->where('code', $language),
            );
        }

        $year = filter_var($filters['year'] ?? null, FILTER_VALIDATE_INT);

        if ($year !== false && $year >= 1000 && $year <= 9999) {
            $query->where('publication_year', $year);
        }

        return $this->applySort($query, (string) ($filters['sort'] ?? 'newest'));
    }

    public function paginate(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $perPage = in_array($perPage, [12, 24, 48], true) ? $perPage : 12;
        $paginator = $this->query($filters)
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()
                ->map(fn (Ebook $ebook): array => $this->card($ebook)),
        );

        return $paginator;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function latest(int $limit): array
    {
        return $this->query(['sort' => 'newest'])
            ->limit(max(1, min(24, $limit)))
            ->get()
            ->map(fn (Ebook $ebook): array => $this->card($ebook))
            ->values()
            ->all();
    }

    public function findBook(string $slug): Ebook
    {
        return $this->query()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Ebook $ebook): array
    {
        $card = $this->card($ebook);

        return [
            ...$card,
            'description' => $ebook->description,
            'isbn' => $ebook->isbn,
            'edition' => $ebook->edition,
            'tags' => $ebook->tags
                ->where('is_active', true)
                ->map(fn ($tag): array => [
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function related(Ebook $ebook, int $limit = 4): array
    {
        $categoryIds = $ebook->categories
            ->where('is_active', true)
            ->modelKeys();

        $query = $this->query()
            ->whereKeyNot($ebook->getKey());

        if ($categoryIds !== []) {
            $query->whereHas(
                'categories',
                fn (Builder $relation): Builder => $relation
                    ->whereIn('categories.id', $categoryIds),
            );
        } elseif ($ebook->publisher_id !== null) {
            $query->where('publisher_id', $ebook->publisher_id);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query
            ->limit(max(1, min(12, $limit)))
            ->get()
            ->map(fn (Ebook $related): array => $this->card($related))
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function filterOptions(): array
    {
        return [
            'categories' => $this->directoryCategories(),
            'authors' => $this->directoryAuthors(),
            'publishers' => $this->directoryPublishers(),
            'collections' => $this->directoryCollections(),
            'languages' => $this->directoryLanguages(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function directoryCategories(): array
    {
        return Category::query()
            ->where('is_active', true)
            ->whereHas('ebooks', fn (Builder $query): Builder => $query->publiclyVisible())
            ->with('parent:id,name,slug')
            ->withCount([
                'ebooks as ebooks_count' => fn (Builder $query): Builder => $query->publiclyVisible(),
            ])
            ->orderByRaw('parent_id IS NOT NULL')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => [
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'count' => $category->ebooks_count,
                'parent' => $category->parent ? [
                    'name' => $category->parent->name,
                    'slug' => $category->parent->slug,
                ] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function directoryAuthors(): array
    {
        return $this->directoryBelongsToMany(Author::query(), 'bio');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function directoryPublishers(): array
    {
        return $this->directoryHasMany(Publisher::query(), 'description');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function directoryCollections(): array
    {
        return $this->directoryHasMany(Collection::query(), 'description');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function directoryLanguages(): array
    {
        return Language::query()
            ->where('is_active', true)
            ->whereHas('ebooks', fn (Builder $query): Builder => $query->publiclyVisible())
            ->withCount([
                'ebooks as ebooks_count' => fn (Builder $query): Builder => $query->publiclyVisible(),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Language $language): array => [
                'name' => $language->name,
                'slug' => $language->code,
                'description' => $language->native_name,
                'count' => $language->ebooks_count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function taxonomy(string $type, string $slug): array
    {
        return match ($type) {
            'category' => $this->taxonomyCategory($slug),
            'author' => $this->taxonomyModel(Author::class, $slug, 'bio'),
            'publisher' => $this->taxonomyModel(Publisher::class, $slug, 'description'),
            'collection' => $this->taxonomyModel(Collection::class, $slug, 'description'),
            default => abort(404),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function card(Ebook $ebook): array
    {
        return [
            'id' => $ebook->getKey(),
            'title' => $ebook->title,
            'subtitle' => $ebook->subtitle,
            'slug' => $ebook->slug,
            'cover_url' => $this->covers->url($ebook->cover_path)
                ?? $this->files->previewUrl($ebook->file),
            'authors' => $ebook->authors
                ->where('is_active', true)
                ->map(fn ($author): array => [
                    'name' => $author->name,
                    'slug' => $author->slug,
                ])
                ->values()
                ->all(),
            'publisher' => $ebook->publisher?->is_active ? [
                'name' => $ebook->publisher->name,
                'slug' => $ebook->publisher->slug,
            ] : null,
            'language' => $ebook->language?->is_active ? [
                'name' => $ebook->language->name,
                'code' => $ebook->language->code,
            ] : null,
            'collection' => $ebook->collection?->is_active ? [
                'name' => $ebook->collection->name,
                'slug' => $ebook->collection->slug,
            ] : null,
            'categories' => $ebook->categories
                ->where('is_active', true)
                ->map(fn ($category): array => [
                    'name' => $category->name,
                    'slug' => $category->slug,
                ])
                ->values()
                ->all(),
            'publication_year' => $ebook->publication_year,
            'page_count' => $ebook->page_count,
            'read_enabled' => $ebook->read_enabled,
            'download_enabled' => $ebook->download_enabled,
            'published_at' => $ebook->published_at?->toIso8601String(),
        ];
    }

    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'title' => $query->orderBy('title')->orderBy('id'),
            'year_desc' => $query
                ->orderByRaw('publication_year IS NULL')
                ->orderByDesc('publication_year')
                ->orderBy('title'),
            'year_asc' => $query
                ->orderByRaw('publication_year IS NULL')
                ->orderBy('publication_year')
                ->orderBy('title'),
            default => $query->orderByDesc('published_at')->orderByDesc('id'),
        };
    }

    private function cleanSlug(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function directoryBelongsToMany(Builder $query, string $descriptionField): array
    {
        return $query
            ->where('is_active', true)
            ->whereHas('ebooks', fn (Builder $books): Builder => $books->publiclyVisible())
            ->withCount([
                'ebooks as ebooks_count' => fn (Builder $books): Builder => $books->publiclyVisible(),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Model $model): array => [
                'name' => $model->getAttribute('name'),
                'slug' => $model->getAttribute('slug'),
                'description' => $model->getAttribute($descriptionField),
                'count' => $model->getAttribute('ebooks_count'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function directoryHasMany(Builder $query, string $descriptionField): array
    {
        return $this->directoryBelongsToMany($query, $descriptionField);
    }

    /**
     * @return array<string, mixed>
     */
    private function taxonomyCategory(string $slug): array
    {
        $category = Category::query()
            ->where('is_active', true)
            ->where('slug', $slug)
            ->whereHas('ebooks', fn (Builder $query): Builder => $query->publiclyVisible())
            ->with('parent:id,name,slug')
            ->firstOrFail();

        return [
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'parent' => $category->parent ? [
                'name' => $category->parent->name,
                'slug' => $category->parent->slug,
            ] : null,
        ];
    }

    /**
     * @param  class-string<Model>  $model
     * @return array<string, mixed>
     */
    private function taxonomyModel(string $model, string $slug, string $descriptionField): array
    {
        $item = $model::query()
            ->where('is_active', true)
            ->where('slug', $slug)
            ->whereHas('ebooks', fn (Builder $query): Builder => $query->publiclyVisible())
            ->firstOrFail();

        return [
            'name' => $item->getAttribute('name'),
            'slug' => $item->getAttribute('slug'),
            'description' => $item->getAttribute($descriptionField),
            'parent' => null,
        ];
    }
}
