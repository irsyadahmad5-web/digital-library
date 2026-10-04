<?php

namespace Tests\Feature;

use App\Modules\Library\Domain\Models\Author;
use App\Modules\Library\Domain\Models\Category;
use App\Modules\Library\Domain\Models\Collection;
use App\Modules\Library\Domain\Models\Ebook;
use App\Modules\Library\Domain\Models\EbookFile;
use App\Modules\Library\Domain\Models\Language;
use App\Modules\Library\Domain\Models\Publisher;
use App\Modules\Library\Domain\Models\Tag;
use App\Modules\Settings\Application\SettingsManager;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        Storage::fake('public');
    }

    public function test_homepage_only_exposes_published_processed_books(): void
    {
        $public = $this->book('Ready Public Book');
        $this->book(
            'Draft Hidden Book',
            ebookOverrides: [
                'publication_status' => 'draft',
                'published_at' => null,
            ],
        );
        $this->book(
            'Pending Hidden Book',
            fileOverrides: ['processing_status' => 'pending'],
        );
        $this->book(
            'Failed Hidden Book',
            fileOverrides: ['processing_status' => 'failed'],
        );

        $this->get('/')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Public/Home')
                    ->has('latestBooks', 1)
                    ->where('latestBooks.0.title', 'Ready Public Book')
                    ->where('latestBooks.0.slug', $public['ebook']->slug),
            );
    }

    public function test_library_search_matches_title_author_category_publisher_and_isbn(): void
    {
        $fixture = $this->book(
            'Arsitektur Sistem Modern',
            ebookOverrides: ['isbn' => '9781234567890'],
        );

        $fixture['author']->forceFill(['name' => 'Ahmad Penulis'])->save();
        $fixture['category']->forceFill(['name' => 'Teknologi Jaringan'])->save();
        $fixture['publisher']->forceFill(['name' => 'Penerbit Nusantara'])->save();

        foreach ([
            'Arsitektur',
            'Ahmad Penulis',
            'Teknologi Jaringan',
            'Penerbit Nusantara',
            '9781234567890',
        ] as $query) {
            $this->get('/library?q='.urlencode($query))
                ->assertOk()
                ->assertInertia(
                    fn (Assert $page) => $page
                        ->component('Public/Library')
                        ->where('books.total', 1)
                        ->where('books.data.0.title', 'Arsitektur Sistem Modern'),
                );
        }

        $this->get('/library?q=tidak-ada-hasil')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('books.total', 0)
                    ->has('books.data', 0),
            );
    }

    public function test_library_filters_by_taxonomy_language_year_and_sort(): void
    {
        $category = $this->category('Komputer');
        $author = $this->author('Irsyad');
        $publisher = $this->publisher('Asyifa Press');
        $collection = $this->collection('Koleksi Utama');
        $language = $this->language('id', 'Indonesia');

        $this->book(
            'Zeta Book',
            ebookOverrides: ['publication_year' => 2026],
            author: $author,
            category: $category,
            publisher: $publisher,
            collection: $collection,
            language: $language,
        );

        $this->book(
            'Alpha Book',
            ebookOverrides: ['publication_year' => 2024],
        );

        $url = '/library?category='.$category->slug
            .'&author='.$author->slug
            .'&publisher='.$publisher->slug
            .'&collection='.$collection->slug
            .'&language='.$language->code
            .'&year=2026';

        $this->get($url)
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('books.total', 1)
                    ->where('books.data.0.title', 'Zeta Book'),
            );

        $this->get('/library?sort=title')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('books.data.0.title', 'Alpha Book')
                    ->where('books.data.1.title', 'Zeta Book'),
            );
    }

    public function test_public_directories_only_count_ready_books(): void
    {
        $category = $this->category('Pendidikan');
        $author = $this->author('Penulis Aktif');
        $publisher = $this->publisher('Penerbit Aktif');
        $collection = $this->collection('Koleksi Aktif');

        $this->book(
            'Ready One',
            author: $author,
            category: $category,
            publisher: $publisher,
            collection: $collection,
        );

        $this->book(
            'Hidden Pending',
            fileOverrides: ['processing_status' => 'pending'],
            author: $author,
            category: $category,
            publisher: $publisher,
            collection: $collection,
        );

        $this->get('/categories')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Public/Directory')
                    ->where('items.0.name', 'Pendidikan')
                    ->where('items.0.count', 1),
            );

        $this->get('/authors')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('items.0.name', 'Penulis Aktif')
                    ->where('items.0.count', 1),
            );

        $this->get('/publishers')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('items.0.name', 'Penerbit Aktif')
                    ->where('items.0.count', 1),
            );

        $this->get('/collections')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('items.0.name', 'Koleksi Aktif')
                    ->where('items.0.count', 1),
            );
    }

    public function test_taxonomy_pages_show_only_matching_public_books(): void
    {
        $category = $this->category('Fiqih');
        $author = $this->author('Ulama Nusantara');

        $this->book(
            'Kitab A',
            author: $author,
            category: $category,
        );
        $this->book('Kitab B');

        $this->get('/category/'.$category->slug)
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Public/Taxonomy')
                    ->where('type', 'category')
                    ->where('taxonomy.name', 'Fiqih')
                    ->where('books.total', 1)
                    ->where('books.data.0.title', 'Kitab A'),
            );

        $this->get('/author/'.$author->slug)
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('type', 'author')
                    ->where('taxonomy.name', 'Ulama Nusantara')
                    ->where('books.total', 1),
            );
    }

    public function test_empty_or_inactive_taxonomy_is_not_publicly_exposed(): void
    {
        $empty = $this->category('Belum Ada Buku');
        $inactive = $this->category('Kategori Nonaktif');
        $inactive->forceFill(['is_active' => false])->save();

        $this->get('/category/'.$empty->slug)->assertNotFound();
        $this->get('/category/'.$inactive->slug)->assertNotFound();
    }

    public function test_book_detail_contains_metadata_tags_and_related_books(): void
    {
        $category = $this->category('Pemrograman');
        $publisher = $this->publisher('Kawan Press');
        $collection = $this->collection('Belajar Digital');
        $language = $this->language('id', 'Indonesia');
        $author = $this->author('Kawan Penulis');

        $fixture = $this->book(
            'Laravel Mendalam',
            ebookOverrides: [
                'description' => 'Deskripsi lengkap ebook.',
                'isbn' => '9781111111111',
                'edition' => 'Edisi Kedua',
                'publication_year' => 2026,
                'page_count' => 321,
            ],
            author: $author,
            category: $category,
            publisher: $publisher,
            collection: $collection,
            language: $language,
        );

        $tag = Tag::query()->create([
            'name' => 'Laravel',
            'slug' => 'laravel',
            'is_active' => true,
        ]);
        $fixture['ebook']->tags()->attach($tag);

        $this->book(
            'Laravel Pendamping',
            category: $category,
        );

        $this->get('/book/'.$fixture['ebook']->slug)
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Public/Book')
                    ->where('book.title', 'Laravel Mendalam')
                    ->where('book.description', 'Deskripsi lengkap ebook.')
                    ->where('book.isbn', '9781111111111')
                    ->where('book.edition', 'Edisi Kedua')
                    ->where('book.page_count', 321)
                    ->where('book.publisher.name', 'Kawan Press')
                    ->where('book.collection.name', 'Belajar Digital')
                    ->where('book.language.name', 'Indonesia')
                    ->where('book.authors.0.name', 'Kawan Penulis')
                    ->where('book.tags.0.name', 'Laravel')
                    ->has('relatedBooks', 1)
                    ->where('relatedBooks.0.title', 'Laravel Pendamping'),
            );
    }

    public function test_non_public_book_detail_returns_404(): void
    {
        $draft = $this->book(
            'Draft Book',
            ebookOverrides: [
                'publication_status' => 'draft',
                'published_at' => null,
            ],
        );

        $failed = $this->book(
            'Failed Processing',
            fileOverrides: ['processing_status' => 'failed'],
        );

        $this->get('/book/'.$draft['ebook']->slug)->assertNotFound();
        $this->get('/book/'.$failed['ebook']->slug)->assertNotFound();
    }

    public function test_about_and_contact_use_public_settings(): void
    {
        app(SettingsManager::class)->updateGroup(
            'general',
            [
                'site_name' => 'Perpustakaan Asyifa',
                'short_name' => 'Asyifa',
                'tagline' => 'Baca bersama',
                'description' => 'Perpustakaan digital untuk masyarakat.',
                'organization_name' => 'Asyifa Digital',
                'address' => 'Jl. Contoh 123',
                'phone' => '08123456789',
                'email' => 'halo@example.com',
            ],
            null,
        );

        $this->get('/about')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Public/Info')
                    ->where('kind', 'about')
                    ->where('description', 'Perpustakaan digital untuk masyarakat.')
                    ->where('organization', 'Asyifa Digital'),
            );

        $this->get('/contact')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('kind', 'contact')
                    ->where('contact.address', 'Jl. Contoh 123')
                    ->where('contact.phone', '08123456789')
                    ->where('contact.email', 'halo@example.com'),
            );
    }

    public function test_library_paginates_public_books(): void
    {
        for ($index = 1; $index <= 13; $index++) {
            $this->book(sprintf('Book %02d', $index));
        }

        $this->get('/library?sort=title')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('books.total', 13)
                    ->has('books.data', 12)
                    ->where('books.current_page', 1)
                    ->where('books.last_page', 2),
            );

        $this->get('/library?sort=title&page=2')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('books.data', 1)
                    ->where('books.current_page', 2),
            );
    }

    public function test_unknown_public_route_renders_custom_404(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Public/NotFound'),
            );
    }

    /**
     * @return array{
     *     ebook: Ebook,
     *     file: EbookFile,
     *     author: Author,
     *     category: Category,
     *     publisher: Publisher,
     *     collection: Collection,
     *     language: Language
     * }
     */
    private function book(
        string $title,
        array $ebookOverrides = [],
        array $fileOverrides = [],
        ?Author $author = null,
        ?Category $category = null,
        ?Publisher $publisher = null,
        ?Collection $collection = null,
        ?Language $language = null,
    ): array {
        $author ??= $this->author('Author '.Str::random(8));
        $category ??= $this->category('Category '.Str::random(8));
        $publisher ??= $this->publisher('Publisher '.Str::random(8));
        $collection ??= $this->collection('Collection '.Str::random(8));
        $language ??= $this->language(
            strtolower(Str::random(2)),
            'Language '.Str::random(8),
        );

        $defaults = [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(8)),
            'description' => 'Deskripsi '.$title,
            'publisher_id' => $publisher->getKey(),
            'language_id' => $language->getKey(),
            'collection_id' => $collection->getKey(),
            'publication_status' => 'published',
            'read_enabled' => true,
            'download_enabled' => true,
            'published_at' => now(),
        ];

        $ebook = Ebook::query()->create([
            ...$defaults,
            ...$ebookOverrides,
        ]);

        $ebook->authors()->attach($author, ['sort_order' => 0]);
        $ebook->categories()->attach($category);

        $fileDefaults = [
            'ebook_id' => $ebook->getKey(),
            'source_type' => 'local',
            'disk' => 'local',
            'path' => 'ebooks/'.$ebook->getKey().'/book.pdf',
            'original_name' => 'book.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'sha256' => hash('sha256', $ebook->slug),
            'verification_status' => 'verified',
            'processing_status' => 'processed',
            'page_count' => $ebook->page_count ?? 100,
            'preview_path' => 'ebooks/generated-previews/'.$ebook->getKey().'/preview.jpg',
            'processed_at' => now(),
        ];

        $file = EbookFile::query()->create([
            ...$fileDefaults,
            ...$fileOverrides,
        ]);

        Storage::disk('public')->put($file->preview_path, 'preview');

        return compact(
            'ebook',
            'file',
            'author',
            'category',
            'publisher',
            'collection',
            'language',
        );
    }

    private function author(string $name): Author
    {
        return Author::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'bio' => 'Bio '.$name,
            'is_active' => true,
        ]);
    }

    private function category(string $name): Category
    {
        return Category::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => 'Deskripsi '.$name,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function publisher(string $name): Publisher
    {
        return Publisher::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => 'Deskripsi '.$name,
            'is_active' => true,
        ]);
    }

    private function collection(string $name): Collection
    {
        return Collection::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => 'Deskripsi '.$name,
            'is_active' => true,
        ]);
    }

    private function language(string $code, string $name): Language
    {
        return Language::query()->create([
            'code' => $code,
            'name' => $name,
            'native_name' => $name,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }
}
