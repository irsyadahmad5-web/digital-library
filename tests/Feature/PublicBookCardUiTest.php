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
