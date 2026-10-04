<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\Domain\Models\Permission;
use App\Modules\Identity\Domain\Models\Role;
use App\Modules\Library\Domain\Models\Author;
use App\Modules\Library\Domain\Models\Category;
use App\Modules\Library\Domain\Models\Collection;
use App\Modules\Library\Domain\Models\Tag;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminMasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessControlSeeder::class);
    }

    public function test_all_master_data_pages_are_available_to_super_admin(): void
    {
        $user = $this->createSuperAdmin();

        foreach (['categories', 'authors', 'publishers', 'tags', 'collections', 'languages'] as $entity) {
            $this->actingAs($user)
                ->get("/admin/master-data/{$entity}")
                ->assertOk()
                ->assertInertia(
                    fn (Assert $page) => $page
                        ->component('Admin/MasterData/Index')
                        ->where('activeEntity', $entity),
                );
        }
    }

    public function test_master_data_requires_specific_permission(): void
    {
        $user = $this->createUserWithPermissions(['admin.access']);

        $this->actingAs($user)
            ->get('/admin/master-data/categories')
            ->assertForbidden();
    }

    public function test_category_supports_one_level_subcategory_and_protects_parent_delete(): void
    {
        $user = $this->createSuperAdmin();

        $this->actingAs($user)->post('/admin/master-data/categories', [
            'parent_id' => null,
            'name' => 'Teknologi Informasi',
            'slug' => '',
            'description' => 'Kategori utama.',
            'sort_order' => 10,
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $root = Category::query()->where('slug', 'teknologi-informasi')->firstOrFail();

        $this->actingAs($user)->post('/admin/master-data/categories', [
            'parent_id' => $root->getKey(),
            'name' => 'Pemrograman',
            'slug' => '',
            'description' => '',
            'sort_order' => 20,
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $child = Category::query()->where('slug', 'pemrograman')->firstOrFail();

        $this->actingAs($user)
            ->from('/admin/master-data/categories')
            ->post('/admin/master-data/categories', [
                'parent_id' => $child->getKey(),
                'name' => 'PHP',
                'slug' => '',
                'description' => '',
                'sort_order' => 30,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('parent_id');

        $this->actingAs($user)
            ->from('/admin/master-data/categories')
            ->delete("/admin/master-data/categories/{$root->getKey()}")
            ->assertSessionHasErrors('ids');

        $this->assertDatabaseHas('categories', [
            'id' => $root->getKey(),
            'deleted_at' => null,
        ]);

        $this->actingAs($user)
            ->delete("/admin/master-data/categories/{$child->getKey()}")
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('categories', ['id' => $child->getKey()]);

        $this->actingAs($user)
            ->delete("/admin/master-data/categories/{$root->getKey()}")
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('categories', ['id' => $root->getKey()]);
    }

    public function test_author_crud_generates_slug_and_creates_audit_events(): void
    {
        $user = $this->createSuperAdmin();

        $this->actingAs($user)->post('/admin/master-data/authors', [
            'name' => 'Budi Santoso',
            'slug' => '',
            'bio' => 'Penulis contoh.',
            'website' => 'https://example.com',
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $author = Author::query()->where('slug', 'budi-santoso')->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->getKey(),
            'event' => 'library.master_data.created',
            'subject_type' => 'authors',
            'subject_id' => (string) $author->getKey(),
        ]);

        $this->actingAs($user)->put("/admin/master-data/authors/{$author->getKey()}", [
            'name' => 'Budi Santoso, M.Kom',
            'slug' => 'budi-santoso',
            'bio' => 'Biografi diperbarui.',
            'website' => '',
            'is_active' => false,
        ])->assertSessionHasNoErrors();

        $author->refresh();

        $this->assertSame('Budi Santoso, M.Kom', $author->name);
        $this->assertFalse($author->is_active);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->getKey(),
            'event' => 'library.master_data.updated',
            'subject_type' => 'authors',
            'subject_id' => (string) $author->getKey(),
        ]);

        $this->actingAs($user)
            ->delete("/admin/master-data/authors/{$author->getKey()}")
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('authors', ['id' => $author->getKey()]);
    }

    public function test_slug_must_remain_unique_even_for_soft_deleted_rows(): void
    {
        $user = $this->createSuperAdmin();

        Author::query()->create([
            'name' => 'Penulis Lama',
            'slug' => 'slug-tetap',
            'is_active' => true,
        ])->delete();

        $this->actingAs($user)
            ->from('/admin/master-data/authors')
            ->post('/admin/master-data/authors', [
                'name' => 'Penulis Baru',
                'slug' => 'Slug Tetap',
                'bio' => '',
                'website' => '',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_language_code_is_normalized_and_unique(): void
    {
        $user = $this->createSuperAdmin();

        $this->actingAs($user)->post('/admin/master-data/languages', [
            'code' => 'ID',
            'name' => 'Bahasa Indonesia',
            'native_name' => 'Bahasa Indonesia',
            'sort_order' => 10,
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('languages', [
            'code' => 'id',
            'name' => 'Bahasa Indonesia',
        ]);

        $this->actingAs($user)
            ->from('/admin/master-data/languages')
            ->post('/admin/master-data/languages', [
                'code' => 'Id',
                'name' => 'Duplikat',
                'native_name' => '',
                'sort_order' => 20,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_search_status_filter_and_pagination_are_applied(): void
    {
        $user = $this->createSuperAdmin();

        Tag::query()->create(['name' => 'Laravel', 'slug' => 'laravel', 'is_active' => true]);
        Tag::query()->create(['name' => 'Vue', 'slug' => 'vue', 'is_active' => true]);
        Tag::query()->create(['name' => 'Arsip Lama', 'slug' => 'arsip-lama', 'is_active' => false]);

        $this->actingAs($user)
            ->get('/admin/master-data/tags?q=Laravel&status=active&per_page=10')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Admin/MasterData/Index')
                    ->where('filters.q', 'Laravel')
                    ->where('filters.status', 'active')
                    ->where('filters.per_page', 10)
                    ->has('items.data', 1)
                    ->where('items.data.0.name', 'Laravel')
                    ->where('items.total', 1),
            );
    }

    public function test_bulk_actions_activate_deactivate_and_soft_delete(): void
    {
        $user = $this->createSuperAdmin();

        $first = Collection::query()->create([
            'name' => 'Referensi',
            'slug' => 'referensi',
            'is_active' => true,
        ]);
        $second = Collection::query()->create([
            'name' => 'Pilihan',
            'slug' => 'pilihan',
            'is_active' => true,
        ]);

        $ids = [$first->getKey(), $second->getKey()];

        $this->actingAs($user)->post('/admin/master-data/collections/bulk', [
            'action' => 'deactivate',
            'ids' => $ids,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, Collection::query()->whereIn('id', $ids)->where('is_active', true)->count());

        $this->actingAs($user)->post('/admin/master-data/collections/bulk', [
            'action' => 'activate',
            'ids' => $ids,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Collection::query()->whereIn('id', $ids)->where('is_active', true)->count());

        $this->actingAs($user)->post('/admin/master-data/collections/bulk', [
            'action' => 'delete',
            'ids' => $ids,
        ])->assertSessionHasNoErrors();

        $this->assertSoftDeleted('collections', ['id' => $first->getKey()]);
        $this->assertSoftDeleted('collections', ['id' => $second->getKey()]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->getKey(),
            'event' => 'library.master_data.bulk',
            'subject_type' => 'collections',
        ]);
    }

    public function test_administrator_role_receives_master_data_permission(): void
    {
        $administrator = Role::query()
            ->where('slug', 'administrator')
            ->firstOrFail();

        $this->assertTrue(
            $administrator->permissions()
                ->where('slug', 'library.manage-master-data')
                ->exists(),
        );
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
            'name' => 'Restricted Master Data Role',
            'slug' => 'restricted-master-data-role',
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
