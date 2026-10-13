<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicBookCardUiTest extends TestCase
{
    public function test_public_book_card_is_cover_first_with_category_reads_and_hover_title(): void
    {
        $card = (string) file_get_contents(
            resource_path('js/components/public/PublicBookCard.vue'),
        );

        $this->assertStringContainsString('book.categories[0].name', $card);
        $this->assertStringContainsString('book.reader_count', $card);
        $this->assertStringContainsString('<Eye', $card);
        $this->assertStringContainsString('md:group-hover:opacity-100', $card);
        $this->assertStringContainsString('md:opacity-0', $card);
        $this->assertStringNotContainsString('pt-3.5', $card);
        $this->assertStringNotContainsString('publication_year', $card);
    }

    public function test_homepage_mobile_book_cards_use_borderless_cover_and_readable_metadata(): void
    {
        $home = (string) file_get_contents(resource_path('js/pages/Public/Home.vue'));
        $card = (string) file_get_contents(resource_path('js/components/public/PublicBookCard.vue'));
        $header = (string) file_get_contents(resource_path('js/components/public/PublicSectionHeader.vue'));

        $this->assertStringContainsString('compact-mobile', $home);
        $this->assertStringContainsString('grid-cols-1 gap-2 sm:mt-5 sm:grid-cols-3', $home);
        $this->assertStringContainsString('compactMobile?: boolean', $card);
        $this->assertStringContainsString('flex w-full min-w-0 items-center gap-3 py-1 sm:block', $card);
        $this->assertStringContainsString('aspect-[3/4] w-[84px] shrink-0', $card);
        $this->assertStringContainsString("compactMobile ? 'hidden sm:flex'", $card);
        $this->assertStringContainsString("compactMobile ? 'hidden sm:block'", $card);
        $this->assertStringContainsString('max-w-full truncate rounded-md bg-brand-soft', $card);
        $this->assertStringContainsString('line-clamp-2 text-[11px] leading-4 text-ink-soft', $card);
        $this->assertStringNotContainsString('min-h-[110px] items-center gap-3 rounded-xl border', $card);
        $this->assertStringNotContainsString('grid-cols-3 gap-2 sm:mt-5 sm:gap-x-4', $home);
        $this->assertStringContainsString("compactMobile ? 'text-[18px]'", $header);
    }

    public function test_category_icon_picker_and_public_icon_surfaces_are_wired(): void
    {
        $master = (string) file_get_contents(
            resource_path('js/pages/Admin/MasterData/Index.vue'),
        );
        $home = (string) file_get_contents(
            resource_path('js/pages/Public/Home.vue'),
        );
        $directory = (string) file_get_contents(
            resource_path('js/components/public/PublicDirectoryCard.vue'),
        );
        $taxonomy = (string) file_get_contents(
            resource_path('js/pages/Public/Taxonomy.vue'),
        );

        $this->assertStringContainsString('<CategoryIconPicker', $master);
        $this->assertStringContainsString("field.type === 'icon'", $master);
        $this->assertStringContainsString('<CategoryIcon :name="item.icon"', $home);
        $this->assertStringContainsString('<CategoryIcon :name="item.icon"', $directory);
        $this->assertStringContainsString('<CategoryIcon :name="taxonomy.icon"', $taxonomy);
    }
}
