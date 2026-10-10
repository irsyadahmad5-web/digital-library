<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminUiShellTest extends TestCase
{
    public function test_admin_shell_keeps_grouped_collapsible_and_mobile_navigation_contract(): void
    {
        $layout = (string) file_get_contents(
            resource_path('js/layouts/AdminLayout.vue'),
        );

        $this->assertStringContainsString('digital-library.admin.sidebar.collapsed.v1', $layout);
        $this->assertStringContainsString("label: 'Overview'", $layout);
        $this->assertStringContainsString("label: 'Library'", $layout);
        $this->assertStringContainsString("label: 'Presentation'", $layout);
        $this->assertStringContainsString("label: 'System'", $layout);
        $this->assertStringContainsString('<SheetShell', $layout);
        $this->assertStringContainsString('aria-label="Buka navigasi admin"', $layout);
        $this->assertStringContainsString('Perluas sidebar', $layout);
        $this->assertStringContainsString('Ringkas sidebar', $layout);
        $this->assertStringContainsString('Lihat situs publik', $layout);
        $this->assertStringContainsString('Keluar', $layout);
    }
}
