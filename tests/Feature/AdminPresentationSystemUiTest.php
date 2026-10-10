<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminPresentationSystemUiTest extends TestCase
{
    public function test_remaining_admin_workspaces_use_v2_design_contract(): void
    {
        $builder = (string) file_get_contents(resource_path('js/pages/Admin/HomepageBuilder/Index.vue'));
        $settings = (string) file_get_contents(resource_path('js/pages/Admin/Settings/Index.vue'));
        $audit = (string) file_get_contents(resource_path('js/pages/Admin/AuditLog.vue'));
        $profile = (string) file_get_contents(resource_path('js/pages/Admin/Profile.vue'));

        $this->assertStringContainsString('<PageHeader', $builder);
        $this->assertStringContainsString('<Switch v-model="section.is_enabled"', $builder);
        $this->assertStringContainsString('form.isDirty', $builder);

        $this->assertStringContainsString('<PageHeader', $settings);
        $this->assertStringContainsString('<Switch v-model="form[String(key)]"', $settings);
        $this->assertStringContainsString('localObjectUrls', $settings);

        $this->assertStringContainsString('<PageHeader', $audit);
        $this->assertStringContainsString('md:hidden', $audit);

        $this->assertStringContainsString('<PageHeader', $profile);
        $this->assertStringContainsString('Sesi aktif', $profile);
        $this->assertStringContainsString('ui-focus-ring', $profile);
    }
}
