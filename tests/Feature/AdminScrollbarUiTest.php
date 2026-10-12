<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminScrollbarUiTest extends TestCase
{
    public function test_admin_layout_enables_auto_hiding_document_and_sidebar_scrollbars(): void
    {
        $layout = (string) file_get_contents(
            resource_path('js/layouts/AdminLayout.vue'),
        );

        $this->assertStringContainsString('admin-shell', $layout);
        $this->assertStringContainsString('admin-scrollbar-scope', $layout);
        $this->assertStringContainsString('admin-scrollbar-active', $layout);
        $this->assertStringContainsString('ui-scrollbar-auto', $layout);
        $this->assertStringContainsString("window.addEventListener('scroll'", $layout);
        $this->assertStringContainsString("window.addEventListener('pointermove'", $layout);
        $this->assertStringContainsString('850', $layout);
    }

    public function test_admin_scrollbar_css_uses_thin_transparent_idle_thumb_and_forced_color_fallback(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('html.admin-scrollbar-scope', $css);
        $this->assertStringContainsString('scrollbar-width: thin', $css);
        $this->assertStringContainsString('scrollbar-color: transparent transparent', $css);
        $this->assertStringContainsString('admin-scrollbar-active::-webkit-scrollbar-thumb', $css);
        $this->assertStringContainsString('.admin-shell :is(.overflow-auto, .overflow-x-auto, .overflow-y-auto)', $css);
        $this->assertStringContainsString('scrollbar-color: auto', $css);
    }
}
