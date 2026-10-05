<?php

namespace Tests\Feature;

use App\Modules\Library\Application\PublicLibrary\PublicLibraryCatalog;
use App\Modules\Library\Domain\Models\Author;
use App\Modules\Library\Domain\Models\Category;
use App\Modules\Library\Domain\Models\Collection;
use App\Modules\Library\Domain\Models\Ebook;
use App\Modules\Library\Domain\Models\EbookDownloadStat;
use App\Modules\Library\Domain\Models\EbookFile;
use App\Modules\Library\Domain\Models\Language;
use App\Modules\Library\Domain\Models\Publisher;
use App\Modules\Library\Domain\Models\Tag;
use App\Modules\Settings\Application\HomepageBuilderManager;
use App\Modules\Settings\Domain\Models\HomepageSection;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SearchDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        Storage::fake('public');
    }

    public function test_search_matches_extended_metadata_and_cross_field_terms(): void
    {
        $author = $this->author('Ahmad Kawan');
        $category = $this->category('Jaringan Komputer');
        $publisher = $this->publisher('Asyifa Press');
        $collection = $this->collection('Koleksi Router');
        $language = $this->language('id', 'Indonesia');
        $tag = $this->tag('MikroTik');

        $fixture = $this->book(
            'Panduan Infrastruktur',
            author: $author,
            category: $category,
            publisher: $publisher,
            collection: $collection,
            language: $language,
            tags: [$tag],
        );

        foreach ([
            'MikroTik',
            'Koleksi Router',
            'Indonesia',
            'Ahmad Jaringan',
        ] as $query) {
            $this->get('/library?q='.urlencode($query))
                ->assertOk()
                ->assertInertia(
                    fn (Assert $page) => $page
                        ->component('Public/Library')
                        ->where('books.total', 1)
                        ->where('books.data.0.id', $fixture['ebook']->getKey())
                        ->where('filters.sort', 'relevance'),
                );
        }
    }

    public function test_relevance_ranking_prioritizes_exact_title_over_relation_and_description_matches(): void
    {
        $exact = $this->book(
            'MikroTik Dasar',
            ebookOverrides: [
                'published_at' => now()->subDays(10),
                'description' => 'Panduan jaringan.',
            ],
        );

        $tag = $this->tag('MikroTik Dasar');
        $this->book(
            'Router Harian',
            ebookOverrides: ['published_at' => now()],
            tags: [$tag],
        );

        $this->book(
            'Catatan Teknisi',
            ebookOverrides: [
                'published_at' => now()->addMinute(),
                'description' => 'Materi MikroTik Dasar untuk teknisi.',
            ],
        );

        $this->get('/library?q='.urlencode('MikroTik Dasar'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('books.total', 3)
                    ->where('books.data.0.id', $exact['ebook']->getKey())
                    ->where('filters.sort', 'relevance'),
            );
    }

    public function test_tag_filter_and_popular_sort_only_use_public_books(): void
    {
        $tag = $this->tag('Laravel');

        $low = $this->book('Laravel Dasar', tags: [$tag]);
        $high = $this->book('Laravel Lanjut', tags: [$tag]);
        $zero = $this->book('Laravel Referensi', tags: [$tag]);
        $hidden = $this->book(
            'Laravel Draft',
            ebookOverrides: [
                'publication_status' => 'draft',
                'published_at' => null,
            ],
            tags: [$tag],
        );

        $this->downloads($low['ebook'], 2);
        $this->downloads($high['ebook'], 12);
        $this->downloads($hidden['ebook'], 100);

        $this->get('/library?tag='.$tag->slug.'&sort=popular')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('books.total', 3)
                    ->where('books.data.0.id', $high['ebook']->getKey())
                    ->where('books.data.1.id', $low['ebook']->getKey())
                    ->where('books.data.2.id', $zero['ebook']->getKey())
                    ->where('filters.tag', $tag->slug)
                    ->where('filters.sort', 'popular'),
            );
    }

    public function test_popular_discovery_requires_real_download_signal(): void
    {
        $catalog = app(PublicLibraryCatalog::class);

        $popular = $this->book('Popular Signal');
        $this->book('No Signal');
        $this->downloads($popular['ebook'], 7);

        $items = $catalog->popular(8);

        $this->assertCount(1, $items);
        $this->assertSame($popular['ebook']->getKey(), $items[0]['id']);
    }

    public function test_recommendations_diversify_topics_before_filling_from_same_topic(): void
    {
        $catalog = app(PublicLibraryCatalog::class);
        $categoryA = $this->category('Kategori A');
        $categoryB = $this->category('Kategori B');
        $categoryC = $this->category('Kategori C');

        $aNewest = $this->book(
            'A Terbaru',
            ebookOverrides: ['published_at' => now()],
            category: $categoryA,
        );
        $this->book(
            'A Kedua',
            ebookOverrides: ['published_at' => now()->subMinute()],
            category: $categoryA,
        );
        $b = $this->book(
            'B Pilihan',
            ebookOverrides: ['published_at' => now()->subMinutes(2)],
            category: $categoryB,
        );
        $c = $this->book(
            'C Pilihan',
            ebookOverrides: ['published_at' => now()->subMinutes(3)],
            category: $categoryC,
        );

        $items = $catalog->recommended(3);
        $ids = array_column($items, 'id');

        $this->assertSame($aNewest['ebook']->getKey(), $ids[0]);
        $this->assertContains($b['ebook']->getKey(), $ids);
        $this->assertContains($c['ebook']->getKey(), $ids);
        $this->assertCount(3, array_unique($ids));
    }

    public function test_related_books_rank_candidates_with_more_shared_metadata_first(): void
    {
        $author = $this->author('Penulis Utama');
        $category = $this->category('Pemrograman');
        $publisher = $this->publisher('Kawan Press');
        $collection = $this->collection('Belajar Web');
        $language = $this->language('id', 'Indonesia');
        $tag = $this->tag('Laravel');

        $source = $this->book(
            'Laravel Utama',
            author: $author,
            category: $category,
            publisher: $publisher,
            collection: $collection,
            language: $language,
            tags: [$tag],
        );

        $strong = $this->book(
            'Laravel Sangat Terkait',
            author: $author,
            category: $category,
            publisher: $publisher,
            collection: $collection,
            language: $language,
            tags: [$tag],
        );

        $weak = $this->book(
            'Laravel Sedikit Terkait',
            category: $category,
        );

        $this->get('/book/'.$source['ebook']->slug)
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('relatedBooks.0.id', $strong['ebook']->getKey())
                    ->where('relatedBooks.1.id', $weak['ebook']->getKey()),
            );
    }

    public function test_homepage_popular_and_recommendation_providers_render_when_enabled(): void
    {
        $popular = $this->book('Buku Paling Populer');
        $this->book('Buku Rekomendasi Lain');
        $this->downloads($popular['ebook'], 20);

        HomepageSection::query()
            ->whereIn('type', ['popular_books', 'recommendations'])
            ->update(['is_enabled' => true]);

        $sections = collect(app(HomepageBuilderManager::class)->publicPayload())
            ->keyBy('type');

        $this->assertTrue($sections->has('popular_books'));
        $this->assertTrue($sections->has('recommendations'));
        $this->assertSame(
            $popular['ebook']->getKey(),
            $sections['popular_books']['data'][0]['id'],
        );
        $this->assertNotEmpty($sections['recommendations']['data']);
    }

    /**
     * @param  list<Tag>  $tags
     * @return array{ebook: Ebook, file: EbookFile}
     */
    private function book(
        string $title,
        array $ebookOverrides = [],
        ?Author $author = null,
        ?Category $category = null,
        ?Publisher $publisher = null,
        ?Collection $collection = null,
        ?Language $language = null,
        array $tags = [],
    ): array {
        $author ??= $this->author('Author '.Str::random(8));
        $category ??= $this->category('Category '.Str::random(8));
        $publisher ??= $this->publisher('Publisher '.Str::random(8));
        $collection ??= $this->collection('Collection '.Str::random(8));
        $language ??= $this->language(
            strtolower(Str::random(8)),
            'Language '.Str::random(8),
        );

        $ebook = Ebook::query()->create([
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
            ...$ebookOverrides,
        ]);

        $ebook->authors()->attach($author, ['sort_order' => 0]);
        $ebook->categories()->attach($category);

        if ($tags !== []) {
            $ebook->tags()->attach(array_map(
                fn (Tag $tag): int => $tag->getKey(),
                $tags,
            ));
        }

        $file = EbookFile::query()->create([
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
            'page_count' => 120,
            'preview_path' => 'ebooks/generated-previews/'.$ebook->getKey().'/preview.jpg',
            'processed_at' => now(),
        ]);

        Storage::disk('public')->put($file->preview_path, 'preview');

        return compact('ebook', 'file');
    }

    private function downloads(Ebook $ebook, int $downloads): void
    {
        EbookDownloadStat::query()->create([
            'ebook_id' => $ebook->getKey(),
            'downloads' => $downloads,
            'last_downloaded_at' => now(),
        ]);
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

    private function tag(string $name): Tag
    {
        return Tag::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
    }
}
