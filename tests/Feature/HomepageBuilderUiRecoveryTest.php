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
        $this->assertStringContainsString('1920 × 720 px', $editor);
        $this->assertStringContainsString('1280 × 720 px', $editor);
        $this->assertStringContainsString('Gambar saja · tanpa teks publik', $editor);
        $this->assertStringNotContainsString('Judul slide', $editor);
        $this->assertStringNotContainsString('Label tombol', $editor);
    }

    public function test_public_slider_is_full_bleed_image_only_horizontal_and_auto_hides_controls(): void
    {
        $slider = (string) file_get_contents(
            resource_path('js/components/public/PublicHeroSlider.vue'),
        );

        $this->assertStringContainsString('displaySlides', $slider);
        $this->assertStringContainsString('<h1 class="sr-only">', $slider);
        $this->assertStringContainsString('class="w-full overflow-hidden bg-[#172033]"', $slider);
        $this->assertStringContainsString('aspect-[16/9]', $slider);
        $this->assertStringContainsString('md:aspect-[8/3]', $slider);
        $this->assertStringContainsString('controlsVisible', $slider);
        $this->assertStringContainsString('pointer-events-none opacity-0', $slider);
        $this->assertStringContainsString('bg-black/15', $slider);
        $this->assertStringContainsString('Slide sebelumnya', $slider);
        $this->assertStringContainsString('Slide berikutnya', $slider);
        $this->assertStringNotContainsString('currentSlide?.title', $slider);
        $this->assertStringNotContainsString('cta_label', preg_replace('/interface HeroSlide.*?}/s', '', $slider) ?: $slider);
        $this->assertStringNotContainsString('bg-gradient-to-t', $slider);
        $this->assertStringNotContainsString('ui-page-shell', $slider);
    }

    public function test_public_layout_uses_auto_hiding_root_scrollbar(): void
    {
        $layout = (string) file_get_contents(
            resource_path('js/layouts/PublicLayout.vue'),
        );
        $css = (string) file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('public-scrollbar-scope', $layout);
        $this->assertStringContainsString('public-scrollbar-active', $layout);
        $this->assertStringContainsString("window.addEventListener('scroll', activateRootScrollbar", $layout);
        $this->assertStringContainsString('html.public-scrollbar-scope', $css);
        $this->assertStringContainsString('html.public-scrollbar-scope.public-scrollbar-active', $css);
    }
}
