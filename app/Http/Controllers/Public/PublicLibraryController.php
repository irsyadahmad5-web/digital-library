<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Application\AnalyticsTracker;
use App\Modules\Library\Application\PublicLibrary\PublicLibraryCatalog;
use App\Modules\Settings\Application\HomepageBuilderManager;
use App\Modules\Settings\Application\SettingsManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class PublicLibraryController extends Controller
{
    public function __construct(
        private readonly PublicLibraryCatalog $catalog,
        private readonly SettingsManager $settings,
        private readonly HomepageBuilderManager $homepageBuilder,
        private readonly AnalyticsTracker $analytics,
    ) {}

    public function home(Request $request): InertiaResponse
    {
        $this->analytics->recordPageView($request, 'home');

        return Inertia::render('Public/Home', [
            'sections' => $this->homepageBuilder->publicPayload(),
        ]);
    }

    public function library(Request $request): InertiaResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:240'],
            'author' => ['nullable', 'string', 'max:240'],
            'publisher' => ['nullable', 'string', 'max:240'],
            'collection' => ['nullable', 'string', 'max:240'],
            'tag' => ['nullable', 'string', 'max:240'],
            'language' => ['nullable', 'string', 'max:32'],
            'year' => ['nullable', 'integer', 'min:1000', 'max:9999'],
            'sort' => ['nullable', 'in:relevance,popular,newest,title,year_desc,year_asc'],
            'per_page' => ['nullable', 'integer', 'in:12,24,48'],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $defaultSort = $search !== '' ? 'relevance' : 'newest';
        $filters['sort'] = (string) ($filters['sort'] ?? $defaultSort);

        $this->analytics->recordPageView($request, 'catalog');

        return Inertia::render('Public/Library', [
            'books' => $this->catalog->paginate(
                $filters,
                (int) ($filters['per_page'] ?? 12),
            ),
            'filters' => [
                'q' => $search,
                'category' => (string) ($filters['category'] ?? ''),
                'author' => (string) ($filters['author'] ?? ''),
                'publisher' => (string) ($filters['publisher'] ?? ''),
                'collection' => (string) ($filters['collection'] ?? ''),
                'tag' => (string) ($filters['tag'] ?? ''),
                'language' => (string) ($filters['language'] ?? ''),
                'year' => $filters['year'] ?? null,
                'sort' => $filters['sort'],
                'per_page' => (int) ($filters['per_page'] ?? 12),
            ],
            'filterOptions' => $this->catalog->filterOptions(),
        ]);
    }

    public function book(Request $request, string $slug): InertiaResponse
    {
        $ebook = $this->catalog->findBook($slug);
        $this->analytics->recordPageView($request, 'book', $ebook);

        return Inertia::render('Public/Book', [
            'book' => $this->catalog->detail($ebook),
            'relatedBooks' => $this->catalog->related($ebook, 4),
        ]);
    }

    public function categories(Request $request): InertiaResponse
    {
        $this->analytics->recordPageView($request, 'directory');

        return $this->directory('categories');
    }

    public function authors(Request $request): InertiaResponse
    {
        $this->analytics->recordPageView($request, 'directory');

        return $this->directory('authors');
    }

    public function publishers(Request $request): InertiaResponse
    {
        $this->analytics->recordPageView($request, 'directory');

        return $this->directory('publishers');
    }

    public function collections(Request $request): InertiaResponse
    {
        $this->analytics->recordPageView($request, 'directory');

        return $this->directory('collections');
    }

    public function category(Request $request, string $slug): InertiaResponse
    {
        return $this->taxonomy($request, 'category', $slug);
    }

    public function author(Request $request, string $slug): InertiaResponse
    {
        return $this->taxonomy($request, 'author', $slug);
    }

    public function publisher(Request $request, string $slug): InertiaResponse
    {
        return $this->taxonomy($request, 'publisher', $slug);
    }

    public function collection(Request $request, string $slug): InertiaResponse
    {
        return $this->taxonomy($request, 'collection', $slug);
    }

    private function directory(string $type): InertiaResponse
    {
        [$title, $singular, $items] = match ($type) {
            'categories' => [
                'Kategori',
                'category',
                $this->catalog->directoryCategories(),
            ],
            'authors' => [
                'Penulis',
                'author',
                $this->catalog->directoryAuthors(),
            ],
            'publishers' => [
                'Penerbit',
                'publisher',
                $this->catalog->directoryPublishers(),
            ],
            'collections' => [
                'Koleksi',
                'collection',
                $this->catalog->directoryCollections(),
            ],
            default => abort(404),
        };

        return Inertia::render('Public/Directory', [
            'title' => $title,
            'type' => $singular,
            'items' => $items,
        ]);
    }

    private function taxonomy(Request $request, string $type, string $slug): InertiaResponse
    {
        $taxonomy = $this->catalog->taxonomy($type, $slug);

        $filters = $request->validate([
            'sort' => ['nullable', 'in:popular,newest,title,year_desc,year_asc'],
            'per_page' => ['nullable', 'integer', 'in:12,24,48'],
        ]);

        $filters[$type] = $slug;
        $this->analytics->recordPageView($request, 'directory');

        return Inertia::render('Public/Taxonomy', [
            'type' => $type,
            'taxonomy' => $taxonomy,
            'books' => $this->catalog->paginate(
                $filters,
                (int) ($filters['per_page'] ?? 12),
            ),
            'filters' => [
                'sort' => (string) ($filters['sort'] ?? 'newest'),
                'per_page' => (int) ($filters['per_page'] ?? 12),
            ],
        ]);
    }

    public function about(Request $request): InertiaResponse
    {
        $this->analytics->recordPageView($request, 'info');
        $general = $this->settings->public()['general'] ?? [];

        return Inertia::render('Public/Info', [
            'kind' => 'about',
            'title' => 'Tentang',
            'description' => (string) ($general['description'] ?? ''),
            'organization' => (string) ($general['organization_name'] ?? ''),
            'contact' => null,
        ]);
    }

    public function contact(Request $request): InertiaResponse
    {
        $this->analytics->recordPageView($request, 'info');
        $general = $this->settings->public()['general'] ?? [];

        return Inertia::render('Public/Info', [
            'kind' => 'contact',
            'title' => 'Kontak',
            'description' => 'Hubungi pengelola perpustakaan melalui informasi berikut.',
            'organization' => (string) ($general['organization_name'] ?? ''),
            'contact' => [
                'address' => (string) ($general['address'] ?? ''),
                'phone' => (string) ($general['phone'] ?? ''),
                'email' => (string) ($general['email'] ?? ''),
            ],
        ]);
    }

    public function notFound(): Response
    {
        return Inertia::render('Public/NotFound')
            ->toResponse(request())
            ->setStatusCode(404);
    }
}
