<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\Domain\Models\Permission;
use App\Modules\Identity\Domain\Models\Role;
use App\Modules\Library\Domain\Models\Author;
use App\Modules\Library\Domain\Models\Category;
use App\Modules\Library\Domain\Models\Collection;
use App\Modules\Library\Domain\Models\Ebook;
use App\Modules\Library\Domain\Models\Language;
use App\Modules\Library\Domain\Models\Publisher;
use App\Modules\Library\Domain\Models\Tag;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminEbookManagementTest extends TestCase
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

    public function test_super_admin_can_open_ebook_index_and_create_form(): void
    {
        $user = $this->createSuperAdmin();

        $this->actingAs($user)
            ->get('/admin/ebooks')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Admin/Ebooks/Index')
                    ->where('ebooks.total', 0),
            );

        $this->actingAs($user)
            ->get('/admin/ebooks/create')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Admin/Ebooks/Form')
                    ->where('ebook', null)
                    ->where('maxCoverMb', 5),
            );
    }

    public function test_ebook_management_requires_specific_permission(): void
    {
        $user = $this->createUserWithPermissions(['admin.access']);

        $this->actingAs($user)
            ->get('/admin/ebooks')
            ->assertForbidden();
    }

    public function test_create_ebook_normalizes_isbn_generates_slug_syncs_relations_and_audits(): void
    {
        $user = $this->createSuperAdmin();
        $master = $this->masterData();

        $response = $this->actingAs($user)->post('/admin/ebooks', $this->payload([
            'title' => 'Belajar Laravel Modern',
            'isbn' => '978-0-306-40615-7',
            'publisher_id' => $master['publisher']->getKey(),
            'language_id' => $master['language']->getKey(),
            'collection_id' => $master['collection']->getKey(),
            'authors' => [
                $master['author_two']->getKey(),
                $master['author_one']->getKey(),
            ],
            'categories' => [$master['category']->getKey()],
            'tags' => [$master['tag']->getKey()],
        ]));

        $ebook = Ebook::query()->where('slug', 'belajar-laravel-modern')->firstOrFail();

        $response->assertRedirect("/admin/ebooks/{$ebook->getKey()}/edit");

        $this->assertSame('9780306406157', $ebook->isbn);
        $this->assertSame($master['publisher']->getKey(), $ebook->publisher_id);
        $this->assertSame($master['language']->getKey(), $ebook->language_id);
        $this->assertSame($master['collection']->getKey(), $ebook->collection_id);

        $this->assertSame(
            [
                $master['author_two']->getKey(),
                $master['author_one']->getKey(),
            ],
            $ebook->authors()->pluck('authors.id')->all(),
        );

        $this->assertDatabaseHas('ebook_author', [
            'ebook_id' => $ebook->getKey(),
            'author_id' => $master['author_two']->getKey(),
            'sort_order' => 0,
        ]);

        $this->assertDatabaseHas('category_ebook', [
            'ebook_id' => $ebook->getKey(),
            'category_id' => $master['category']->getKey(),
        ]);

        $this->assertDatabaseHas('ebook_tag', [
            'ebook_id' => $ebook->getKey(),
            'tag_id' => $master['tag']->getKey(),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->getKey(),
            'event' => 'library.ebook.created',
            'subject_type' => 'ebook',
            'subject_id' => (string) $ebook->getKey(),
        ]);
    }

    public function test_invalid_and_duplicate_isbn_are_rejected(): void
    {
        $user = $this->createSuperAdmin();

        $this->actingAs($user)
            ->from('/admin/ebooks/create')
            ->post('/admin/ebooks', $this->payload([
                'title' => 'ISBN Salah',
                'isbn' => '9780306406158',
            ]))
            ->assertSessionHasErrors('isbn');

        Ebook::query()->create([
            'title' => 'Existing',
            'slug' => 'existing',
            'isbn' => '9780306406157',
            'publication_status' => 'draft',
            'read_enabled' => true,
            'download_enabled' => true,
        ]);

        $this->actingAs($user)
            ->from('/admin/ebooks/create')
            ->post('/admin/ebooks', $this->payload([
                'title' => 'Duplikat ISBN',
                'isbn' => '978-0-306-40615-7',
            ]))
            ->assertSessionHasErrors('isbn');
    }

    public function test_published_status_sets_timestamp_and_returning_to_draft_clears_it(): void
    {
        $user = $this->createSuperAdmin();

        $this->actingAs($user)->post('/admin/ebooks', $this->payload([
            'title' => 'Buku Terbit',
            'publication_status' => 'published',
        ]))->assertSessionHasNoErrors();

        $ebook = Ebook::query()->where('slug', 'buku-terbit')->firstOrFail();

        $this->assertNotNull($ebook->published_at);

        $this->actingAs($user)->put(
            "/admin/ebooks/{$ebook->getKey()}",
            $this->payload([
                'title' => 'Buku Terbit',
                'slug' => $ebook->slug,
                'publication_status' => 'draft',
            ]),
        )->assertSessionHasNoErrors();

        $ebook->refresh();

        $this->assertSame('draft', $ebook->publication_status);
        $this->assertNull($ebook->published_at);
    }

    public function test_cover_can_be_uploaded_and_replaced_using_method_spoofing(): void
    {
        Storage::fake('public');

        $user = $this->createSuperAdmin();

        $this->actingAs($user)->post('/admin/ebooks', $this->payload([
            'title' => 'Dengan Cover',
            'cover' => UploadedFile::fake()->image('cover-one.jpg', 600, 900),
        ]))->assertSessionHasNoErrors();

        $ebook = Ebook::query()->where('slug', 'dengan-cover')->firstOrFail();
        $oldCover = $ebook->cover_path;

        $this->assertNotNull($oldCover);
        Storage::disk('public')->assertExists($oldCover);

        $this->actingAs($user)->post(
            "/admin/ebooks/{$ebook->getKey()}",
            $this->payload([
                '_method' => 'put',
                'title' => 'Dengan Cover',
                'slug' => $ebook->slug,
                'cover' => UploadedFile::fake()->image('cover-two.png', 600, 900),
            ]),
        )->assertSessionHasNoErrors();

        $ebook->refresh();

        $this->assertNotSame($oldCover, $ebook->cover_path);
        Storage::disk('public')->assertMissing($oldCover);
        Storage::disk('public')->assertExists($ebook->cover_path);
    }

    public function test_edit_syncs_relations_and_creates_update_audit(): void
    {
        $user = $this->createSuperAdmin();
        $master = $this->masterData();

        $ebook = Ebook::query()->create([
            'title' => 'Relasi Lama',
            'slug' => 'relasi-lama',
            'publication_status' => 'draft',
            'read_enabled' => true,
            'download_enabled' => true,
        ]);

        $ebook->authors()->attach($master['author_one']->getKey(), ['sort_order' => 0]);

        $this->actingAs($user)->put(
            "/admin/ebooks/{$ebook->getKey()}",
            $this->payload([
                'title' => 'Relasi Baru',
                'slug' => 'relasi-lama',
                'authors' => [$master['author_two']->getKey()],
                'categories' => [$master['category']->getKey()],
                'tags' => [$master['tag']->getKey()],
            ]),
        )->assertSessionHasNoErrors();

        $ebook->refresh();

        $this->assertSame('Relasi Baru', $ebook->title);
        $this->assertSame(
            [$master['author_two']->getKey()],
            $ebook->authors()->pluck('authors.id')->all(),
        );

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->getKey(),
            'event' => 'library.ebook.updated',
            'subject_type' => 'ebook',
            'subject_id' => (string) $ebook->getKey(),
        ]);
    }

    public function test_index_search_filters_and_pagination_work(): void
    {
        $user = $this->createSuperAdmin();
        $master = $this->masterData();

        $target = Ebook::query()->create([
            'title' => 'Target Laravel',
            'slug' => 'target-laravel',
            'language_id' => $master['language']->getKey(),
            'publication_status' => 'published',
            'read_enabled' => true,
            'download_enabled' => false,
            'published_at' => now(),
        ]);
        $target->authors()->attach($master['author_one']->getKey(), ['sort_order' => 0]);
        $target->categories()->attach($master['category']->getKey());

        Ebook::query()->create([
            'title' => 'Buku Lain',
            'slug' => 'buku-lain',
            'publication_status' => 'draft',
            'read_enabled' => false,
            'download_enabled' => false,
        ]);

        $url = '/admin/ebooks?q=Laravel&status=published&access=readable'
            .'&category_id='.$master['category']->getKey()
            .'&author_id='.$master['author_one']->getKey()
            .'&language_id='.$master['language']->getKey()
            .'&per_page=10';

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Admin/Ebooks/Index')
                    ->where('filters.q', 'Laravel')
                    ->where('filters.status', 'published')
                    ->where('filters.access', 'readable')
                    ->where('filters.per_page', 10)
                    ->has('ebooks.data', 1)
                    ->where('ebooks.data.0.title', 'Target Laravel')
                    ->where('ebooks.total', 1),
            );
    }

    public function test_bulk_actions_change_publication_access_and_soft_delete(): void
    {
        $user = $this->createSuperAdmin();

        $first = $this->ebook('Bulk Satu', 'bulk-satu');
        $second = $this->ebook('Bulk Dua', 'bulk-dua');
        $ids = [$first->getKey(), $second->getKey()];

        $this->actingAs($user)->post('/admin/ebooks/bulk', [
            'action' => 'publish',
            'ids' => $ids,
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            2,
            Ebook::query()
                ->whereIn('id', $ids)
                ->where('publication_status', 'published')
                ->whereNotNull('published_at')
                ->count(),
        );

        $this->actingAs($user)->post('/admin/ebooks/bulk', [
            'action' => 'disable_read',
            'ids' => $ids,
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            0,
            Ebook::query()->whereIn('id', $ids)->where('read_enabled', true)->count(),
        );

        $this->actingAs($user)->post('/admin/ebooks/bulk', [
            'action' => 'delete',
            'ids' => $ids,
        ])->assertSessionHasNoErrors();

        $this->assertSoftDeleted('ebooks', ['id' => $first->getKey()]);
        $this->assertSoftDeleted('ebooks', ['id' => $second->getKey()]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->getKey(),
            'event' => 'library.ebook.bulk',
            'subject_type' => 'ebook',
        ]);
    }

    public function test_single_delete_is_soft_delete_and_audited(): void
    {
        $user = $this->createSuperAdmin();
        $ebook = $this->ebook('Hapus Saya', 'hapus-saya');

        $this->actingAs($user)
            ->delete("/admin/ebooks/{$ebook->getKey()}")
            ->assertRedirect('/admin/ebooks');

        $this->assertSoftDeleted('ebooks', ['id' => $ebook->getKey()]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->getKey(),
            'event' => 'library.ebook.deleted',
            'subject_type' => 'ebook',
            'subject_id' => (string) $ebook->getKey(),
        ]);
    }

    public function test_administrator_role_receives_ebook_permission(): void
    {
        $administrator = Role::query()
            ->where('slug', 'administrator')
            ->firstOrFail();

        $this->assertTrue(
            $administrator->permissions()
                ->where('slug', 'library.manage-ebooks')
                ->exists(),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Contoh Ebook',
            'subtitle' => '',
            'slug' => '',
            'isbn' => '',
            'description' => '',
            'publication_year' => null,
            'edition' => '',
            'page_count' => null,
            'publisher_id' => null,
            'language_id' => null,
            'collection_id' => null,
            'publication_status' => 'draft',
            'read_enabled' => true,
            'download_enabled' => true,
            'authors' => [],
            'categories' => [],
            'tags' => [],
            'remove_cover' => false,
        ], $overrides);
    }

    /**
     * @return array<string, Author|Category|Collection|Language|Publisher|Tag>
     */
    private function masterData(): array
    {
        $authorOne = Author::query()->create([
            'name' => 'Penulis Satu',
            'slug' => 'penulis-satu',
            'is_active' => true,
        ]);
        $authorTwo = Author::query()->create([
            'name' => 'Penulis Dua',
            'slug' => 'penulis-dua',
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Teknologi',
            'slug' => 'teknologi',
            'is_active' => true,
        ]);
        $publisher = Publisher::query()->create([
            'name' => 'Penerbit Contoh',
            'slug' => 'penerbit-contoh',
            'is_active' => true,
        ]);
        $tag = Tag::query()->create([
            'name' => 'Laravel',
            'slug' => 'laravel',
            'is_active' => true,
        ]);
        $collection = Collection::query()->create([
            'name' => 'Referensi',
            'slug' => 'referensi',
            'is_active' => true,
        ]);
        $language = Language::query()->create([
            'code' => 'id',
            'name' => 'Bahasa Indonesia',
            'native_name' => 'Bahasa Indonesia',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        return [
            'author_one' => $authorOne,
            'author_two' => $authorTwo,
            'category' => $category,
            'publisher' => $publisher,
            'tag' => $tag,
            'collection' => $collection,
            'language' => $language,
        ];
    }

    private function ebook(string $title, string $slug): Ebook
    {
        return Ebook::query()->create([
            'title' => $title,
            'slug' => $slug,
            'publication_status' => 'draft',
            'read_enabled' => true,
            'download_enabled' => true,
        ]);
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
            'name' => 'Restricted Ebook Role',
            'slug' => 'restricted-ebook-role',
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
