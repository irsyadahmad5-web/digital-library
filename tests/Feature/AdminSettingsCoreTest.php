<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\Domain\Models\Permission;
use App\Modules\Identity\Domain\Models\Role;
use App\Modules\Settings\Application\SettingsManager;
use App\Modules\Settings\Domain\Models\HomepageSection;
use App\Modules\Settings\Domain\Models\Setting;
use App\Modules\Settings\Support\HomepageSectionRegistry;
use App\Modules\Settings\Support\SettingsRegistry;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSettingsCoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            AccessControlSeeder::class,
            SettingsSeeder::class,
        ]);

        app(SettingsManager::class)->flush();
    }

    public function test_settings_defaults_are_seeded_from_registry(): void
    {
        $expected = collect(app(SettingsRegistry::class)->groups())
            ->sum(fn (array $group): int => count($group['fields']));

        $this->assertSame($expected, Setting::query()->count());
        $this->assertSame(
            count(app(HomepageSectionRegistry::class)->types()),
            HomepageSection::query()->count(),
        );
    }

    public function test_super_admin_can_open_settings_page(): void
    {
        $user = $this->createSuperAdmin();

        $response = $this->actingAs($user)->get('/admin/settings/general');

        $response->assertOk();
    }

    public function test_settings_page_requires_manage_settings_permission(): void
    {
        $user = $this->createUserWithPermissions(['admin.access']);

        $this->actingAs($user)
            ->get('/admin/settings/general')
            ->assertForbidden();
    }

    public function test_updating_settings_invalidates_cache_and_is_audited(): void
    {
        $user = $this->createSuperAdmin();
        $manager = app(SettingsManager::class);

        $this->assertSame('Digital Library', $manager->get('general', 'site_name'));

        $response = $this->actingAs($user)->post('/admin/settings/general', [
            'site_name' => 'Perpustakaan Amal Baca',
            'short_name' => 'Amal Baca',
            'tagline' => 'Membaca untuk semua.',
            'description' => 'Koleksi bacaan digital.',
            'organization_name' => '',
            'address' => '',
            'phone' => '',
            'email' => '',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(
            'Perpustakaan Amal Baca',
            $manager->get('general', 'site_name'),
        );

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->getKey(),
            'event' => 'admin.settings.updated',
            'subject_type' => 'settings',
            'subject_id' => 'general',
        ]);
    }

    public function test_pdf_upload_limit_cannot_exceed_300_mb(): void
    {
        $user = $this->createSuperAdmin();

        $response = $this->actingAs($user)
            ->from('/admin/settings/uploads')
            ->post('/admin/settings/uploads', [
                'max_pdf_mb' => 301,
                'chunk_size_mb' => 10,
                'checksum_enabled' => true,
                'max_cover_mb' => 5,
            ]);

        $response->assertSessionHasErrors('max_pdf_mb');
        $this->assertSame(
            300,
            app(SettingsManager::class)->get('uploads', 'max_pdf_mb'),
        );
    }

    public function test_logo_upload_is_stored_and_setting_is_updated(): void
    {
        Storage::fake('public');

        $user = $this->createSuperAdmin();

        $response = $this->actingAs($user)->post('/admin/settings/general', [
            'site_name' => 'Digital Library',
            'short_name' => 'Digital Library',
            'tagline' => '',
            'description' => '',
            'organization_name' => '',
            'address' => '',
            'phone' => '',
            'email' => '',
            'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
        ]);

        $response->assertSessionHasNoErrors();

        $path = (string) app(SettingsManager::class)->get('general', 'logo_path');

        $this->assertStringStartsWith('branding/logo-', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_public_payload_does_not_expose_private_admin_settings(): void
    {
        $public = app(SettingsManager::class)->public();

        $this->assertArrayHasKey('general', $public);
        $this->assertArrayHasKey('reader', $public);
        $this->assertArrayHasKey('downloads', $public);
        $this->assertArrayNotHasKey('uploads', $public);
        $this->assertArrayNotHasKey('storage', $public);
        $this->assertArrayNotHasKey('enabled', $public['maintenance']);
    }

    public function test_public_maintenance_returns_503_but_admin_login_remains_available(): void
    {
        $manager = app(SettingsManager::class);
        $manager->updateGroup('maintenance', [
            'enabled' => true,
            'message' => 'Sedang maintenance.',
            'contact_text' => '',
        ], null);

        $this->get('/')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '300');

        $this->get('/admin/login')->assertOk();
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
            'name' => 'Settings Test Role',
            'slug' => 'settings-test-role',
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
