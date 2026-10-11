<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthSystemUiTest extends TestCase
{
    public function test_auth_and_public_system_states_use_v2_visual_contract(): void
    {
        $layout = (string) file_get_contents(resource_path('js/layouts/AuthLayout.vue'));
        $login = (string) file_get_contents(resource_path('js/pages/Auth/Login.vue'));
        $forgot = (string) file_get_contents(resource_path('js/pages/Auth/ForgotPassword.vue'));
        $reset = (string) file_get_contents(resource_path('js/pages/Auth/ResetPassword.vue'));
        $maintenance = (string) file_get_contents(resource_path('js/pages/Public/Maintenance.vue'));
        $notFound = (string) file_get_contents(resource_path('js/pages/Public/NotFound.vue'));
        $info = (string) file_get_contents(resource_path('js/pages/Public/Info.vue'));

        $this->assertStringContainsString('Area administrator', $layout);
        $this->assertStringContainsString('bg-canvas', $layout);
        $this->assertStringContainsString('<AuthLayout', $login);
        $this->assertStringContainsString('<Checkbox v-model="form.remember"', $login);
        $this->assertStringContainsString('Tampilkan password', $login);
        $this->assertStringContainsString('<AuthLayout', $forgot);
        $this->assertStringContainsString('<AuthLayout', $reset);
        $this->assertStringContainsString('autocomplete="new-password"', $reset);

        $this->assertStringContainsString('bg-canvas', $maintenance);
        $this->assertStringContainsString('Admin tetap dapat mengakses', $maintenance);
        $this->assertStringContainsString('<PublicLayout>', $notFound);
        $this->assertStringContainsString('404 · Tidak ditemukan', $notFound);
        $this->assertStringContainsString('<PublicLayout>', $info);
        $this->assertStringContainsString('ui-reading-measure', $info);
    }
}
