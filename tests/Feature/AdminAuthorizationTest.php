<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\Domain\Models\Permission;
use App\Modules\Identity\Domain\Models\Role;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_access_permission_allows_dashboard_access(): void
    {
        $user = $this->createUserWithPermissions(['admin.access']);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
    }

    public function test_audit_log_requires_its_own_permission(): void
    {
        $user = $this->createUserWithPermissions(['admin.access']);

        $this->actingAs($user)->get('/admin')->assertOk();
        $this->actingAs($user)->get('/admin/audit-log')->assertForbidden();
    }

    public function test_analytics_requires_its_own_permission(): void
    {
        $withoutAnalytics = $this->createUserWithPermissions(['admin.access']);
        $withAnalytics = $this->createUserWithPermissions([
            'admin.access',
            'admin.view-analytics',
        ]);

        $this->actingAs($withoutAnalytics)
            ->get('/admin/analytics')
            ->assertForbidden();

        $this->actingAs($withAnalytics)
            ->get('/admin/analytics')
            ->assertOk();
    }

    public function test_admin_security_headers_are_applied(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );
    }

    /**
     * @param  list<string>  $permissions
     */
    private function createUserWithPermissions(array $permissions): User
    {
        $this->seed(AccessControlSeeder::class);

        $role = Role::query()->create([
            'name' => 'Test Role',
            'slug' => 'test-role-'.Str::lower(Str::random(6)),
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
