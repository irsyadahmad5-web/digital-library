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
use App\Modules\Library\Domain\Models\Tag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;

class PublicLibraryCatalog
{
    public function __construct(
        private readonly EbookCoverManager $covers,
        private readonly EbookFileManager $files,
        private readonly PublicLibraryCache $cache,
    ) {}

    public function query(array $filters = [], bool $includeTags = false): Builder
    {
        $relations = [
            'authors:id,name,slug,is_active',
            'categories:id,parent_id,name,slug,is_active',
            'publisher:id,name,slug,is_active',
            'language:id,code,name,native_name,is_active',
            'collection:id,name,slug,is_active',
            'file:id,ebook_id,source_type,disk,path,external_url,size_bytes,sha256,etag,verification_status,processing_status,preview_path',
        ];

        if ($includeTags) {
            $relations[] = 'tags:id,name,slug,is_active';
        }

        $query = Ebook::query()
            ->publiclyVisible()
            ->with($relations);

        $search = $this->normalizeSearch($filters['q'] ?? '');

        if ($search !== '') {
            $this->applySearch($query, $search);
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

        if ($slug = $this->cleanSlug($filters['tag'] ?? null)) {
            $query->whereHas(
                'tags',
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

        $defaultSort = $search !== '' ? 'relevance' : 'newest';

        return $this->applySort(
            $query,
            (string) ($filters['sort'] ?? $defaultSort),
            $search,
        );
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
        $limit = max(1, min(24, $limit));

        return $this->cache->remember(
            'catalog:latest:'.$limit,
            120,
            fn (): array => $this->query(['sort' => 'newest'])
                ->limit($limit)
                ->get()
                ->map(fn (Ebook $ebook): array => $this->card($ebook))
                ->values()
                ->all(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function popular(int $limit): array
    {
        $limit = max(1, min(24, $limit));

        return $this->cache->remember(
            'catalog:popular:'.$limit,
            120,
            fn (): array => $this->query(['sort' => 'popular'])
                ->whereHas(
                    'downloadStat',
                    fn (Builder $query): Builder => $query->where('downloads', '>', 0),
                )
                ->limit($limit)
                ->get()
                ->map(fn (Ebook $ebook): array => $this->card($ebook))
                ->values()
                ->all(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recommended(int $limit): array
    {
        $limit = max(1, min(24, $limit));

        return $this->cache->remember(
            'catalog:recommended:'.$limit,
            120,
            fn (): array => $this->buildRecommended($limit),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildRecommended(int $limit): array
    {
        $candidateLimit = min(96, max(24, $limit * 8));

        /** @var SupportCollection<int, Ebook> $candidates */
        $candidates = $this->query(['sort' => 'newest'])
            ->limit($candidateLimit)
            ->get();

        if ($candidates->isEmpty()) {
            return [];
        }

        $selected = collect();
        $seenTopics = [];

        foreach ($candidates as $ebook) {
            $topicKey = $this->discoveryTopicKey($ebook);

            if ($topicKey === null || isset($seenTopics[$topicKey])) {
                continue;
            }

            $selected->push($ebook);
            $seenTopics[$topicKey] = true;

            if ($selected->count() >= $limit) {
                break;
            }
        }

        if ($selected->count() < $limit) {
            $selectedIds = $selected
                ->map(fn (Ebook $item): int|string => $item->getKey())
                ->all();

            foreach ($candidates as $ebook) {
                if (in_array($ebook->getKey(), $selectedIds, true)) {
                    continue;
                }

                $selected->push($ebook);
                $selectedIds[] = $ebook->getKey();

                if ($selected->count() >= $limit) {
                    break;
                }
            }
        }

        return $selected
            ->map(fn (Ebook $ebook): array => $this->card($ebook))
            ->values()
            ->all();
    }

    public function findBook(string $slug): Ebook
    {
        return $this->query([], true)
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
            'reader_revision' => $ebook->file?->readerRevision(),
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
        $limit = max(1, min(12, $limit));

        $categoryIds = $ebook->categories
            ->where('is_active', true)
            ->modelKeys();
        $tagIds = $ebook->tags
            ->where('is_active', true)
            ->modelKeys();
        $authorIds = $ebook->authors
            ->where('is_active', true)
            ->modelKeys();

        $hasSignals = $categoryIds !== []
            || $tagIds !== []
            || $authorIds !== []
            || $ebook->publisher_id !== null
            || $ebook->collection_id !== null
            || $ebook->language_id !== null;

        $query = $this->query([], true)
            ->whereKeyNot($ebook->getKey());

        if ($hasSignals) {
            $query->where(function (Builder $builder) use (
                $categoryIds,
                $tagIds,
                $authorIds,
                $ebook,
            ): void {
                $hasCondition = false;

                if ($categoryIds !== []) {
                    $builder->whereHas(
                        'categories',
                        fn (Builder $relation): Builder => $relation
                            ->whereIn('categories.id', $categoryIds),
                    );
                    $hasCondition = true;
                }

                if ($tagIds !== []) {
                    $method = $hasCondition ? 'orWhereHas' : 'whereHas';
                    $builder->{$method}(
                        'tags',
                        fn (Builder $relation): Builder => $relation
                            ->whereIn('tags.id', $tagIds),
                    );
                    $hasCondition = true;
                }

                if ($authorIds !== []) {
                    $method = $hasCondition ? 'orWhereHas' : 'whereHas';
                    $builder->{$method}(
                        'authors',
                        fn (Builder $relation): Builder => $relation
                            ->whereIn('authors.id', $authorIds),
                    );
                    $hasCondition = true;
                }

                foreach ([
                    'publisher_id' => $ebook->publisher_id,
                    'collection_id' => $ebook->collection_id,
                    'language_id' => $ebook->language_id,
                ] as $column => $value) {
                    if ($value === null) {
                        continue;
                    }

                    if ($hasCondition) {
                        $builder->orWhere($column, $value);
                    } else {
                        $builder->where($column, $value);
                        $hasCondition = true;
                    }
                }
            });
        }

        /** @var SupportCollection<int, Ebook> $candidates */
        $candidates = $query
            ->limit($hasSignals ? 60 : $limit)
            ->get();

        if ($hasSignals) {
            $candidates = $candidates
                ->sort(function (Ebook $left, Ebook $right) use ($ebook): int {
                    $scoreComparison = $this->relatedScore($right, $ebook)
                        <=> $this->relatedScore($left, $ebook);

                    if ($scoreComparison !== 0) {
                        return $scoreComparison;
                    }

                    $rightPublished = $right->published_at?->getTimestamp() ?? 0;
                    $leftPublished = $left->published_at?->getTimestamp() ?? 0;

                    if ($rightPublished !== $leftPublished) {
                        return $rightPublished <=> $leftPublished;
                    }

                    return $right->getKey() <=> $left->getKey();
                })
                ->values();
        }

        return $candidates
            ->take($limit)
            ->map(fn (Ebook $related): array => $this->card($related))
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function filterOptions(): array
    {
        return $this->cache->remember(
            'directory:filter-options',
            300,
            fn (): array => [
                'categories' => $this->directoryCategories(),
                'authors' => $this->directoryAuthors(),
                'publishers' => $this->directoryPublishers(),
                'collections' => $this->directoryCollections(),
                'tags' => $this->directoryTags(),
                'languages' => $this->directoryLanguages(),
            ],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function directoryCategories(): array
    {
        return $this->cache->remember(
            'directory:categories',
            300,
            fn (): array => Category::query()
                ->select(['id', 'parent_id', 'name', 'slug', 'description', 'is_active', 'sort_order'])
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
                ->all(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function directoryAuthors(): array
    {
        return $this->cache->remember(
            'directory:authors',
            300,
            fn (): array => $this->directoryBelongsToMany(Author::query(), 'bio'),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function directoryPublishers(): array
    {
        return $this->cache->remember(
            'directory:publishers',
            300,
            fn (): array => $this->directoryHasMany(Publisher::query(), 'description'),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function directoryCollections(): array
    {
        return $this->cache->remember(
            'directory:collections',
            300,
            fn (): array => $this->directoryHasMany(Collection::query(), 'description'),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function directoryTags(): array
    {
        return $this->cache->remember(
            'directory:tags',
            300,
            fn (): array => Tag::query()
                ->select(['id', 'name', 'slug', 'is_active'])
                ->where('is_active', true)
                ->whereHas('ebooks', fn (Builder $query): Builder => $query->publiclyVisible())
                ->withCount([
                    'ebooks as ebooks_count' => fn (Builder $query): Builder => $query->publiclyVisible(),
                ])
                ->orderByDesc('ebooks_count')
                ->orderBy('name')
                ->get()
                ->map(fn (Tag $tag): array => [
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'description' => null,
                    'count' => $tag->ebooks_count,
                ])
                ->values()
                ->all(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function directoryLanguages(): array
    {
        return $this->cache->remember(
            'directory:languages',
            300,
            fn (): array => Language::query()
                ->select(['id', 'code', 'name', 'native_name', 'is_active', 'sort_order'])
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
                ->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function taxonomy(string $type, string $slug): array
    {
        return $this->cache->remember(
            'taxonomy:'.$type.':'.$slug,
            300,
            fn (): array => match ($type) {
                'category' => $this->taxonomyCategory($slug),
                'author' => $this->taxonomyModel(Author::class, $slug, 'bio'),
                'publisher' => $this->taxonomyModel(Publisher::class, $slug, 'description'),
                'collection' => $this->taxonomyModel(Collection::class, $slug, 'description'),
                default => abort(404),
            },
        );
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

    private function applySearch(Builder $query, string $search): void
    {
        foreach ($this->searchTerms($search) as $term) {
            $like = '%'.$term.'%';

            $query->where(function (Builder $builder) use ($like): void {
                $builder
                    ->where('ebooks.title', 'like', $like)
                    ->orWhere('ebooks.subtitle', 'like', $like)
                    ->orWhere('ebooks.isbn', 'like', $like)
                    ->orWhere('ebooks.description', 'like', $like)
                    ->orWhereHas('authors', fn (Builder $relation): Builder => $relation
                        ->where('is_active', true)
                        ->where('name', 'like', $like))
                    ->orWhereHas('categories', fn (Builder $relation): Builder => $relation
                        ->where('is_active', true)
                        ->where('name', 'like', $like))
                    ->orWhereHas('publisher', fn (Builder $relation): Builder => $relation
                        ->where('is_active', true)
                        ->where('name', 'like', $like))
                    ->orWhereHas('collection', fn (Builder $relation): Builder => $relation
                        ->where('is_active', true)
                        ->where('name', 'like', $like))
                    ->orWhereHas('tags', fn (Builder $relation): Builder => $relation
                        ->where('is_active', true)
                        ->where('name', 'like', $like))
                    ->orWhereHas('language', fn (Builder $relation): Builder => $relation
                        ->where('is_active', true)
                        ->where(function (Builder $language) use ($like): void {
                            $language
                                ->where('name', 'like', $like)
                                ->orWhere('native_name', 'like', $like)
                                ->orWhere('code', 'like', $like);
                        }));
            });
        }
    }

    private function applySort(
        Builder $query,
        string $sort,
        string $search = '',
    ): Builder {
        if ($sort === 'relevance' && $search !== '') {
            return $this->applyRelevanceSort($query, $search);
        }

        return match ($sort) {
            'popular' => $query
                ->orderByRaw(
                    'COALESCE((SELECT downloads FROM ebook_download_stats WHERE ebook_download_stats.ebook_id = ebooks.id), 0) DESC',
                )
                ->orderByDesc('published_at')
                ->orderByDesc('ebooks.id'),
            'title' => $query->orderBy('title')->orderBy('ebooks.id'),
            'year_desc' => $query
                ->orderByRaw('publication_year IS NULL')
                ->orderByDesc('publication_year')
                ->orderBy('title'),
            'year_asc' => $query
                ->orderByRaw('publication_year IS NULL')
                ->orderBy('publication_year')
                ->orderBy('title'),
            default => $query
                ->orderByDesc('published_at')
                ->orderByDesc('ebooks.id'),
        };
    }

    private function applyRelevanceSort(Builder $query, string $search): Builder
    {
        $needle = mb_strtolower($search);
        $contains = '%'.$needle.'%';
        $prefix = $needle.'%';

        $expressions = [
            'CASE WHEN LOWER(ebooks.title) = ? THEN 180 WHEN LOWER(ebooks.title) LIKE ? THEN 140 WHEN LOWER(ebooks.title) LIKE ? THEN 100 ELSE 0 END',
            'CASE WHEN LOWER(COALESCE(ebooks.subtitle, \'\')) LIKE ? THEN 45 ELSE 0 END',
            'CASE WHEN LOWER(COALESCE(ebooks.isbn, \'\')) = ? THEN 160 WHEN LOWER(COALESCE(ebooks.isbn, \'\')) LIKE ? THEN 80 ELSE 0 END',
            'CASE WHEN LOWER(COALESCE(ebooks.description, \'\')) LIKE ? THEN 15 ELSE 0 END',
            'CASE WHEN EXISTS (
                SELECT 1 FROM ebook_author ea
                INNER JOIN authors a ON a.id = ea.author_id
                WHERE ea.ebook_id = ebooks.id
                  AND a.deleted_at IS NULL
                  AND a.is_active = 1
                  AND LOWER(a.name) LIKE ?
            ) THEN 70 ELSE 0 END',
            'CASE WHEN EXISTS (
                SELECT 1 FROM category_ebook ce
                INNER JOIN categories c ON c.id = ce.category_id
                WHERE ce.ebook_id = ebooks.id
                  AND c.deleted_at IS NULL
                  AND c.is_active = 1
                  AND LOWER(c.name) LIKE ?
            ) THEN 55 ELSE 0 END',
            'CASE WHEN EXISTS (
                SELECT 1 FROM publishers p
                WHERE p.id = ebooks.publisher_id
                  AND p.deleted_at IS NULL
                  AND p.is_active = 1
                  AND LOWER(p.name) LIKE ?
            ) THEN 45 ELSE 0 END',
            'CASE WHEN EXISTS (
                SELECT 1 FROM ebook_tag et
                INNER JOIN tags t ON t.id = et.tag_id
                WHERE et.ebook_id = ebooks.id
                  AND t.deleted_at IS NULL
                  AND t.is_active = 1
                  AND LOWER(t.name) LIKE ?
            ) THEN 45 ELSE 0 END',
            'CASE WHEN EXISTS (
                SELECT 1 FROM collections co
                WHERE co.id = ebooks.collection_id
                  AND co.deleted_at IS NULL
                  AND co.is_active = 1
                  AND LOWER(co.name) LIKE ?
            ) THEN 35 ELSE 0 END',
            'CASE WHEN EXISTS (
                SELECT 1 FROM languages l
                WHERE l.id = ebooks.language_id
                  AND l.deleted_at IS NULL
                  AND l.is_active = 1
                  AND (
                      LOWER(l.name) LIKE ?
                      OR LOWER(COALESCE(l.native_name, \'\')) LIKE ?
                      OR LOWER(l.code) LIKE ?
                  )
            ) THEN 20 ELSE 0 END',
        ];

        $bindings = [
            $needle,
            $prefix,
            $contains,
            $contains,
            $needle,
            $contains,
            $contains,
            $contains,
            $contains,
            $contains,
            $contains,
            $contains,
            $contains,
            $contains,
        ];

        foreach ($this->searchTerms($search) as $term) {
            $expressions[] = 'CASE WHEN LOWER(ebooks.title) LIKE ? THEN 18 ELSE 0 END';
            $bindings[] = '%'.mb_strtolower($term).'%';
        }

        return $query
            ->orderByRaw('('.implode(' + ', $expressions).') DESC', $bindings)
            ->orderByDesc('published_at')
            ->orderByDesc('ebooks.id');
    }

    /**
     * @return list<string>
     */
    private function searchTerms(string $search): array
    {
        $terms = preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_slice(array_unique($terms), 0, 6));
    }

    private function normalizeSearch(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $value = trim((string) $value);
        $value = str_replace(['%', '_'], ' ', $value);
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return mb_substr(trim($value), 0, 120);
    }

    private function cleanSlug(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function discoveryTopicKey(Ebook $ebook): ?string
    {
        $category = $ebook->categories
            ->where('is_active', true)
            ->first();

        if ($category !== null) {
            return 'category:'.$category->getKey();
        }

        if ($ebook->collection_id !== null) {
            return 'collection:'.$ebook->collection_id;
        }

        if ($ebook->publisher_id !== null) {
            return 'publisher:'.$ebook->publisher_id;
        }

        return null;
    }

    private function relatedScore(Ebook $candidate, Ebook $source): int
    {
        $score = 0;

        $score += count(array_intersect(
            $candidate->categories->where('is_active', true)->modelKeys(),
            $source->categories->where('is_active', true)->modelKeys(),
        )) * 8;

        $score += count(array_intersect(
            $candidate->tags->where('is_active', true)->modelKeys(),
            $source->tags->where('is_active', true)->modelKeys(),
        )) * 6;

        $score += count(array_intersect(
            $candidate->authors->where('is_active', true)->modelKeys(),
            $source->authors->where('is_active', true)->modelKeys(),
        )) * 5;

        if (
            $source->publisher_id !== null
            && $candidate->publisher_id === $source->publisher_id
        ) {
            $score += 3;
        }

        if (
            $source->collection_id !== null
            && $candidate->collection_id === $source->collection_id
        ) {
            $score += 3;
        }

        if (
            $source->language_id !== null
            && $candidate->language_id === $source->language_id
        ) {
            $score += 1;
        }

        return $score;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function directoryBelongsToMany(Builder $query, string $descriptionField): array
    {
        return $query
            ->select(['id', 'name', 'slug', $descriptionField, 'is_active'])
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
