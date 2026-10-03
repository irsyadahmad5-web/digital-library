<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use App\Modules\Identity\Domain\Models\Role;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/admin/login');
    }

    public function test_super_admin_can_login_and_login_is_audited(): void
    {
        $user = $this->createSuperAdmin();

        $response = $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->getKey(),
            'event' => 'auth.login.success',
        ]);
    }

    public function test_user_without_admin_permission_cannot_login_to_admin(): void
    {
        $user = User::factory()->create();

        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_suspended_admin_cannot_login(): void
    {
        $user = $this->createSuperAdmin([
            'status' => UserStatus::Suspended,
        ]);

        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        $user = $this->createSuperAdmin();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/admin/login')->post('/admin/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Terlalu banyak percobaan login',
            session('errors')->first('email'),
        );
    }

    public function test_authenticated_admin_can_logout(): void
    {
        $user = $this->createSuperAdmin();

        $response = $this->actingAs($user)->post('/admin/logout');

        $response->assertRedirect('/admin/login');
        $this->assertGuest();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->getKey(),
            'event' => 'auth.logout',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createSuperAdmin(array $attributes = []): User
    {
        $this->seed(AccessControlSeeder::class);

        $user = User::factory()->create($attributes);
        $role = Role::query()->where('slug', 'super-admin')->firstOrFail();

        $user->roles()->attach($role);

        return $user;
    }
}
