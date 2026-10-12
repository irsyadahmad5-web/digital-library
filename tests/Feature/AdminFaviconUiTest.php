<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminFaviconUiTest extends TestCase
{
    public function test_base_inertia_document_exposes_configured_favicon_for_admin_and_public_pages(): void
    {
        $blade = (string) file_get_contents(resource_path('views/app.blade.php'));

        $this->assertStringContainsString("\$generalProps['favicon_url']", $blade);
        $this->assertStringContainsString('<link rel="icon"', $blade);
        $this->assertStringContainsString('rel="apple-touch-icon"', $blade);
    }
}
