<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\Domain\Models\Role;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProfileSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_requires_current_password_and_is_audited(): void
    {
        $user = $this->createSuperAdmin();

        $this->actingAs($user)->patch('/admin/profile', [
            'name' => 'Nama Baru',
            'email' => 'baru@example.com',
            'current_password' => 'wrong-password',
        ])->assertSessionHasErrors('current_password');

        $response = $this->actingAs($user)->patch('/admin/profile', [
            'name' => 'Nama Baru',
            'email' => 'baru@example.com',
            'current_password' => 'password',
        ]);

        $response->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame('baru@example.com', $user->email);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->getKey(),
            'event' => 'admin.profile.updated',
        ]);
    }

    public function test_password_update_requires_strong_password_and_is_audited(): void
    {
        $user = $this->createSuperAdmin();

        $this->actingAs($user)->put('/admin/password', [
            'current_password' => 'password',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])->assertSessionHasErrors('password');

        $response = $this->actingAs($user)->put('/admin/password', [
            'current_password' => 'password',
            'password' => 'BetterPass!234',
            'password_confirmation' => 'BetterPass!234',
        ]);

        $response->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::check('BetterPass!234', $user->password));

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->getKey(),
            'event' => 'admin.password.updated',
        ]);
    }

    private function createSuperAdmin(): User
    {
        $this->seed(AccessControlSeeder::class);

        $user = User::factory()->create();
        $role = Role::query()->where('slug', 'super-admin')->firstOrFail();
        $user->roles()->attach($role);

        return $user;
    }
}
