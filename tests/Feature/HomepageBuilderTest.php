<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\Domain\Models\Permission;
use App\Modules\Identity\Domain\Models\Role;
use App\Modules\Library\Domain\Models\Category;
use App\Modules\Library\Domain\Models\Collection;
use App\Modules\Library\Domain\Models\Ebook;
use App\Modules\Library\Domain\Models\EbookFile;
use App\Modules\Settings\Application\HomepageBuilderManager;
use App\Modules\Settings\Domain\Models\HomepageSection;
use App\Modules\Settings\Support\HomepageSectionRegistry;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomepageBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            AccessControlSeeder::class,
            SettingsSeeder::class,
        ]);
    }

    public function test_builder_defaults_are_created_from_registry(): void
    {
        $types = app(HomepageSectionRegistry::class)->types();

        $this->assertSame($types, HomepageSection::query()
            ->orderBy('sort_order')
            ->pluck('type')
            ->all());

        $this->assertSame(count($types), HomepageSection::query()->count());

        $popular = HomepageSection::query()->where('type', 'popular_books')->firstOrFail();
        $recommendations = HomepageSection::query()->where('type', 'recommendations')->firstOrFail();
        $statistics = HomepageSection::query()->where('type', 'statistics')->firstOrFail();

        $this->assertFalse($popular->is_enabled);
        $this->assertFalse($recommendations->is_enabled);
        $this->assertFalse($statistics->is_enabled);
    }

    public function test_super_admin_can_open_builder_and_provider_status_is_exposed(): void
    {
        $user = $this->createSuperAdmin();

        $this->actingAs($user)
            ->get('/admin/homepage-builder')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Admin/HomepageBuilder/Index')
                    ->has('sections', 8)
                    ->where('schemas.hero.provider_available', true)
                    ->where('schemas.latest_books.provider_available', true)
                    ->where('schemas.popular_books.provider_available', false)
                    ->where('schemas.recommendations.provider_available', false)
                    ->where('schemas.statistics.provider_available', false),
            );
    }

    public function test_builder_requires_manage_settings_permission(): void
    {
        $user = $this->createUserWithPermissions(['admin.access']);

        $this->actingAs($user)
            ->get('/admin/homepage-builder')
            ->assertForbidden();

        $this->actingAs($user)
            ->put('/admin/homepage-builder', ['sections' => []])
            ->assertForbidden();
    }

    public function test_builder_save_reorders_normalizes_config_and_is_audited(): void
    {
        $user = $this->createSuperAdmin();
        $sections = HomepageSection::query()
            ->orderBy('sort_order')
            ->get()
            ->keyBy('type');

        $orderedTypes = [
            'categories',
            'hero',
            'search',
            'latest_books',
            'collections',
            'popular_books',
            'recommendations',
            'statistics',
        ];

        $payload = [];

        foreach ($orderedTypes as $type) {
            $section = $sections->get($type);

            $config = match ($type) {
                'hero' => [
                    'eyebrow' => 'Kawan Library',
                    'title' => 'Homepage Baru',
                    'subtitle' => 'Susunan homepage diatur admin.',
                    'show_access_card' => false,
                    'cta_label' => 'Jelajahi',
                    'cta_href' => 'https://evil.example.com',
                ],
                'latest_books' => [
                    'eyebrow' => 'Baru',
                    'title' => 'Terbaru',
                    'limit' => 999,
                    'show_view_all' => true,
                ],
                'categories' => [
                    'eyebrow' => 'Topik',
                    'title' => 'Pilih kategori',
                    'limit' => 2,
                    'show_view_all' => false,
                ],
                default => [],
            };

            $payload[] = [
                'id' => $section->getKey(),
                'title' => strtoupper($type),
                'is_enabled' => ! in_array($type, ['recommendations', 'statistics'], true),
                'config' => $config,
            ];
        }

        $this->actingAs($user)
            ->put('/admin/homepage-builder', [
                'sections' => $payload,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $categories = HomepageSection::query()->where('type', 'categories')->firstOrFail();
        $hero = HomepageSection::query()->where('type', 'hero')->firstOrFail();
        $latest = HomepageSection::query()->where('type', 'latest_books')->firstOrFail();

        $this->assertSame(10, $categories->sort_order);
        $this->assertSame(20, $hero->sort_order);
        $this->assertSame(40, $latest->sort_order);
        $this->assertFalse($categories->config_json['show_view_all']);
        $this->assertSame(4, $categories->config_json['limit']);
        $this->assertSame(24, $latest->config_json['limit']);
        $this->assertSame('/library', $hero->config_json['cta_href']);
        $this->assertFalse($hero->config_json['show_access_card']);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->getKey(),
            'event' => 'admin.homepage-builder.updated',
            'subject_type' => 'homepage',
            'subject_id' => 'sections',
        ]);
    }

    public function test_public_home_follows_builder_order_and_enabled_state(): void
    {
        $category = Category::query()->create([
            'name' => 'Teknologi',
            'slug' => 'teknologi',
            'description' => 'Kategori teknologi.',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $collection = Collection::query()->create([
            'name' => 'Pilihan Kawan',
            'slug' => 'pilihan-kawan',
            'description' => 'Koleksi pilihan.',
            'is_active' => true,
        ]);

        $this->publicBook('Buku Publik', $category, $collection);

        $sections = HomepageSection::query()
            ->orderBy('sort_order')
            ->get()
            ->keyBy('type');

        $payload = [
            $this->sectionPayload($sections['categories'], true, [
                'title' => 'Kategori Pilihan',
                'limit' => 8,
            ]),
            $this->sectionPayload($sections['search'], true, [
                'title' => 'Cari Sekarang',
            ]),
            $this->sectionPayload($sections['popular_books'], true, [
                'title' => 'Populer',
                'limit' => 8,
            ]),
            $this->sectionPayload($sections['collections'], true, [
                'title' => 'Koleksi Pilihan',
                'limit' => 8,
            ]),
            $this->sectionPayload($sections['hero'], false),
            $this->sectionPayload($sections['latest_books'], false),
            $this->sectionPayload($sections['recommendations'], false),
            $this->sectionPayload($sections['statistics'], false),
        ];

        app(HomepageBuilderManager::class)->save($payload);

        $this->get('/')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Public/Home')
                    ->has('sections', 3)
                    ->where('sections.0.type', 'categories')
                    ->where('sections.0.config.title', 'Kategori Pilihan')
                    ->where('sections.0.data.0.name', 'Teknologi')
                    ->where('sections.1.type', 'search')
                    ->where('sections.1.config.title', 'Cari Sekarang')
                    ->where('sections.2.type', 'collections')
                    ->where('sections.2.data.0.name', 'Pilihan Kawan'),
            );
    }

    public function test_unavailable_provider_is_never_rendered_even_when_enabled(): void
    {
        foreach (['popular_books', 'recommendations', 'statistics'] as $type) {
            HomepageSection::query()
                ->where('type', $type)
                ->update(['is_enabled' => true]);
        }

        $publicTypes = collect(app(HomepageBuilderManager::class)->publicPayload())
            ->pluck('type')
            ->all();

        $this->assertNotContains('popular_books', $publicTypes);
        $this->assertNotContains('recommendations', $publicTypes);
        $this->assertNotContains('statistics', $publicTypes);
    }

    public function test_old_partial_builder_is_completed_without_reordering_existing_sections(): void
    {
        HomepageSection::query()->delete();

        $old = [
            ['type' => 'hero', 'sort_order' => 10],
            ['type' => 'latest_books', 'sort_order' => 20],
            ['type' => 'popular_books', 'sort_order' => 30],
            ['type' => 'categories', 'sort_order' => 40],
        ];

        foreach ($old as $row) {
            HomepageSection::query()->create([
                'type' => $row['type'],
                'title' => Str::headline($row['type']),
                'config_json' => ['legacy' => true],
                'is_enabled' => true,
                'sort_order' => $row['sort_order'],
            ]);
        }

        app(HomepageBuilderManager::class)->ensureDefaults();

        $this->assertSame(8, HomepageSection::query()->count());

        foreach ($old as $row) {
            $section = HomepageSection::query()->where('type', $row['type'])->firstOrFail();

            $this->assertSame($row['sort_order'], $section->sort_order);
            $this->assertTrue($section->config_json['legacy']);
        }

        $newSortOrders = HomepageSection::query()
            ->whereIn('type', ['search', 'collections', 'recommendations', 'statistics'])
            ->pluck('sort_order')
            ->all();

        foreach ($newSortOrders as $sortOrder) {
            $this->assertGreaterThan(40, $sortOrder);
        }

        $this->assertSame(count($newSortOrders), count(array_unique($newSortOrders)));
    }

    public function test_legacy_homepage_settings_url_redirects_to_builder(): void
    {
        $user = $this->createSuperAdmin();

        $this->actingAs($user)
            ->get('/admin/settings/homepage')
            ->assertRedirect('/admin/homepage-builder');
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionPayload(
        HomepageSection $section,
        bool $enabled,
        array $config = [],
    ): array {
        return [
            'id' => $section->getKey(),
            'title' => $section->title,
            'is_enabled' => $enabled,
            'config' => $config,
        ];
    }

    private function publicBook(
        string $title,
        Category $category,
        Collection $collection,
    ): Ebook {
        $ebook = Ebook::query()->create([
            'title' => $title,
            'slug' => Str::slug($title),
            'collection_id' => $collection->getKey(),
            'publication_status' => 'published',
            'read_enabled' => true,
            'download_enabled' => true,
            'published_at' => now(),
            'page_count' => 120,
        ]);

        $ebook->categories()->attach($category);

        EbookFile::query()->create([
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
            'processed_at' => now(),
        ]);

        return $ebook;
    }

    private function createSuperAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('slug', 'super-admin')->firstOrFail();

        $user->roles()->attach($role);

        return $user;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function createUserWithPermissions(array $permissions): User
    {
        $role = Role::query()->create([
            'name' => 'Homepage Builder Test Role',
            'slug' => 'homepage-builder-test-role',
            'is_system' => false,
        ]);

        $role->permissions()->sync(
            Permission::query()
                ->whereIn('slug', $permissions)
                ->pluck('id')
                ->all(),
        );

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
