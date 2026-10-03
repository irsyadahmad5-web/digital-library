<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_can_be_requested_without_revealing_account_state(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post('/admin/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);

        $unknownResponse = $this->post('/admin/forgot-password', [
            'email' => 'unknown@example.com',
        ]);

        $unknownResponse->assertSessionHas('status');
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $response = $this->post('/admin/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewSecure!2345',
            'password_confirmation' => 'NewSecure!2345',
        ]);

        $response->assertRedirect('/admin/login');

        $user->refresh();

        $this->assertTrue(Hash::check('NewSecure!2345', $user->password));
        $this->assertNotNull($user->password_changed_at);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->getKey(),
            'event' => 'auth.password_reset.completed',
        ]);
    }
}
