<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomepageBuilderUiRecoveryTest extends TestCase
{
    public function test_builder_does_not_structured_clone_vue_proxy_props(): void
    {
        $builder = (string) file_get_contents(
            resource_path('js/pages/Admin/HomepageBuilder/Index.vue'),
        );

        $this->assertStringNotContainsString('structuredClone(', $builder);
        $this->assertStringContainsString('JSON.parse(JSON.stringify(source))', $builder);
        $this->assertStringContainsString('section.type === \'hero\'', $builder);
        $this->assertStringContainsString('config.slides = []', $builder);
    }

    public function test_builder_has_non_blank_recovery_and_slider_model_has_safe_default(): void
    {
        $builder = (string) file_get_contents(
            resource_path('js/pages/Admin/HomepageBuilder/Index.vue'),
        );
        $editor = (string) file_get_contents(
            resource_path('js/components/admin/AdminHeroSliderEditor.vue'),
        );

        $this->assertStringContainsString('Section homepage belum dapat ditampilkan', $builder);
        $this->assertStringContainsString('Muat ulang builder', $builder);
        $this->assertStringContainsString('only: [\'sections\', \'schemas\']', $builder);
        $this->assertStringContainsString('default: () => []', $editor);
        $this->assertStringContainsString('1920 × 820 px', $editor);
        $this->assertStringContainsString('1080 × 1350 px', $editor);
    }

    public function test_public_slider_has_image_and_image_less_starter_visual_paths(): void
    {
        $slider = (string) file_get_contents(
            resource_path('js/components/public/PublicHeroSlider.vue'),
        );

        $this->assertStringContainsString('hasImage(slide)', $slider);
        $this->assertStringContainsString('starterSurfaceClass(index)', $slider);
        $this->assertStringContainsString('md:aspect-[16/7]', $slider);
        $this->assertStringContainsString('Slide sebelumnya', $slider);
        $this->assertStringContainsString('Slide berikutnya', $slider);
    }
}
