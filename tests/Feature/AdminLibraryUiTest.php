<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminLibraryUiTest extends TestCase
{
    public function test_admin_library_workspaces_use_v2_responsive_components(): void
    {
        $index = (string) file_get_contents(resource_path('js/pages/Admin/Ebooks/Index.vue'));
        $form = (string) file_get_contents(resource_path('js/pages/Admin/Ebooks/Form.vue'));
        $master = (string) file_get_contents(resource_path('js/pages/Admin/MasterData/Index.vue'));

        $this->assertStringContainsString('<PageHeader', $index);
        $this->assertStringContainsString('<ConfirmDialog', $index);
        $this->assertStringContainsString('md:hidden', $index);
        $this->assertStringContainsString('mobileFiltersOpen', $index);

        $this->assertStringContainsString('<PageHeader', $form);
        $this->assertStringContainsString('<Switch v-model="form.read_enabled"', $form);
        $this->assertStringContainsString('<Switch v-model="form.download_enabled"', $form);

        $this->assertStringContainsString('<PageHeader', $master);
        $this->assertStringContainsString('<ConfirmDialog', $master);
        $this->assertStringContainsString('md:hidden', $master);
        $this->assertStringNotContainsString('window.confirm', $master);
    }
}
