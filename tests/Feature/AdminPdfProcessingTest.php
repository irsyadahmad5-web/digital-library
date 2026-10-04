<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\Domain\Models\Role;
use App\Modules\Library\Application\Pdf\ExternalPdfMaterializer;
use App\Modules\Library\Application\Pdf\PdfToolchain;
use App\Modules\Library\Application\Storage\EbookFileManager;
use App\Modules\Library\Domain\Models\Ebook;
use App\Modules\Library\Domain\Models\EbookFile;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\SettingsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class AdminPdfProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            AccessControlSeeder::class,
            SettingsSeeder::class,
        ]);
    }

    public function test_local_pdf_processing_extracts_metadata_page_count_preview_and_audits(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = $this->createSuperAdmin();
        $ebook = $this->ebook();
        $file = $this->localSource($ebook, $this->pdfContent());

        $this->fakeToolchain(
            pages: 42,
            title: 'Metadata Title',
            author: 'Metadata Author',
        );

        $response = $this->actingAs($user)
            ->postJson("/admin/ebooks/{$ebook->getKey()}/processing")
            ->assertOk()
            ->json('source');

        $file->refresh();
        $ebook->refresh();

        $this->assertSame('processed', $file->processing_status);
        $this->assertSame(42, $file->page_count);
        $this->assertSame('Metadata Title', $file->pdf_metadata['title']);
        $this->assertSame('Metadata Author', $file->pdf_metadata['author']);
        $this->assertSame(42, $ebook->page_count);
        $this->assertNotNull($file->processed_at);
        $this->assertNull($file->processing_error);
        $this->assertNotNull($file->preview_path);
        Storage::disk('public')->assertExists($file->preview_path);

        $this->assertSame('processed', $response['processing_status']);
        $this->assertSame(42, $response['page_count']);
        $this->assertNotNull($response['preview_url']);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->getKey(),
            'event' => 'library.ebook.pdf.processed',
            'subject_type' => 'ebook',
            'subject_id' => (string) $ebook->getKey(),
        ]);
    }

    public function test_processing_failure_is_persisted_with_clear_status(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = $this->createSuperAdmin();
        $ebook = $this->ebook();
        $file = $this->localSource($ebook, $this->pdfContent());

        $this->mock(PdfToolchain::class, function (MockInterface $mock): void {
            $mock->shouldReceive('inspect')
                ->once()
                ->andThrow(new DomainException('PDF structure is damaged.'));
            $mock->shouldNotReceive('renderFirstPage');
        });

        $this->actingAs($user)
            ->postJson("/admin/ebooks/{$ebook->getKey()}/processing")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $file->refresh();

        $this->assertSame('failed', $file->processing_status);
        $this->assertNull($file->processed_at);
        $this->assertStringContainsString('damaged', (string) $file->processing_error);
        $this->assertNull($file->preview_path);
    }

    public function test_local_checksum_change_is_rejected_before_pdf_toolchain(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = $this->createSuperAdmin();
        $ebook = $this->ebook();
        $file = $this->localSource(
            $ebook,
            $this->pdfContent(),
            str_repeat('a', 64),
        );

        $this->mock(PdfToolchain::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('inspect');
            $mock->shouldNotReceive('renderFirstPage');
        });

        $this->actingAs($user)
            ->postJson("/admin/ebooks/{$ebook->getKey()}/processing")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $file->refresh();

        $this->assertSame('failed', $file->processing_status);
        $this->assertStringContainsString(
            'Checksum private PDF berubah',
            (string) $file->processing_error,
        );
    }

    public function test_reprocessing_replaces_old_generated_preview(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = $this->createSuperAdmin();
        $ebook = $this->ebook();
        $file = $this->localSource($ebook, $this->pdfContent());

        $oldPreview = 'ebooks/generated-previews/'.$ebook->getKey().'/old.jpg';
        Storage::disk('public')->put($oldPreview, 'old-preview');

        $file->forceFill([
            'processing_status' => 'processed',
            'page_count' => 10,
            'pdf_metadata' => ['pages' => 10],
            'preview_path' => $oldPreview,
            'processed_at' => now()->subDay(),
        ])->save();

        $this->fakeToolchain(pages: 12);

        $this->actingAs($user)
            ->postJson("/admin/ebooks/{$ebook->getKey()}/processing")
            ->assertOk();

        $file->refresh();

        $this->assertSame(12, $file->page_count);
        $this->assertNotSame($oldPreview, $file->preview_path);
        Storage::disk('public')->assertMissing($oldPreview);
        Storage::disk('public')->assertExists($file->preview_path);
    }

    public function test_replacing_source_resets_processing_and_deletes_old_preview(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $ebook = $this->ebook();
        $firstContent = $this->pdfContent('A');
        $file = $this->localSource($ebook, $firstContent);

        $oldPreview = 'ebooks/generated-previews/'.$ebook->getKey().'/old.jpg';
        Storage::disk('public')->put($oldPreview, 'old-preview');

        $file->forceFill([
            'processing_status' => 'processed',
            'page_count' => 20,
            'pdf_metadata' => ['pages' => 20],
            'preview_path' => $oldPreview,
            'processed_at' => now(),
        ])->save();

        $newPath = 'ebooks/'.$ebook->getKey().'/new.pdf';
        $newContent = $this->pdfContent('B');
        Storage::disk('local')->put($newPath, $newContent);

        $updated = app(EbookFileManager::class)->attachLocal(
            $ebook,
            [
                'path' => $newPath,
                'original_name' => 'new.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($newContent),
                'sha256' => hash('sha256', $newContent),
            ],
            null,
        );

        $this->assertSame('pending', $updated->processing_status);
        $this->assertNull($updated->page_count);
        $this->assertNull($updated->pdf_metadata);
        $this->assertNull($updated->preview_path);
        $this->assertNull($updated->processed_at);
        Storage::disk('public')->assertMissing($oldPreview);
        Storage::disk('local')->assertMissing($file->path);
        Storage::disk('local')->assertExists($newPath);
    }

    public function test_processing_command_handles_pending_and_recovers_stale_processing(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $first = $this->ebook('Pending PDF');
        $firstFile = $this->localSource(
            $first,
            $this->pdfContent('P'),
            null,
            'ebooks/pending.pdf',
        );

        $second = $this->ebook('Stale PDF');
        $secondFile = $this->localSource(
            $second,
            $this->pdfContent('S'),
            null,
            'ebooks/stale.pdf',
        );
        $secondFile->forceFill([
            'processing_status' => 'processing',
            'processing_started_at' => now()->subHour(),
        ])->save();

        $this->mock(PdfToolchain::class, function (MockInterface $mock): void {
            $mock->shouldReceive('inspect')
                ->twice()
                ->andReturn($this->metadata(7));
            $mock->shouldReceive('renderFirstPage')
                ->twice()
                ->andReturnUsing(function (string $pdf, string $prefix): string {
                    file_put_contents($prefix.'.jpg', 'jpeg-preview');

                    return $prefix.'.jpg';
                });
        });

        $this->artisan('ebooks:pdf:process --limit=5')
            ->expectsOutput('Checked 2 PDF source(s): 2 processed, 0 failed.')
            ->assertSuccessful();

        $this->assertSame('processed', $firstFile->fresh()->processing_status);
        $this->assertSame('processed', $secondFile->fresh()->processing_status);
    }

    public function test_external_source_processing_uses_materialized_temp_file_and_cleans_it(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = $this->createSuperAdmin();
        $ebook = $this->ebook();
        $file = EbookFile::query()->create([
            'ebook_id' => $ebook->getKey(),
            'source_type' => 'external_url',
            'external_url' => 'https://8.8.8.8/book.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'verification_status' => 'verified',
            'processing_status' => 'pending',
        ]);

        $tempPath = 'pdf-processing-temp/materialized.pdf';
        $content = $this->pdfContent('E');
        Storage::disk('local')->put($tempPath, $content);

        $this->mock(ExternalPdfMaterializer::class, function (MockInterface $mock) use ($tempPath, $content): void {
            $mock->shouldReceive('materialize')
                ->once()
                ->with('https://8.8.8.8/book.pdf')
                ->andReturn([
                    'path' => $tempPath,
                    'size_bytes' => strlen($content),
                    'sha256' => hash('sha256', $content),
                    'mime_type' => 'application/pdf',
                ]);
        });

        $this->fakeToolchain(pages: 9);

        $this->actingAs($user)
            ->postJson("/admin/ebooks/{$ebook->getKey()}/processing")
            ->assertOk();

        $file->refresh();

        $this->assertSame('processed', $file->processing_status);
        $this->assertSame(9, $file->page_count);
        $this->assertSame(hash('sha256', $content), $file->sha256);
        Storage::disk('local')->assertMissing($tempPath);
    }

    public function test_edit_form_exposes_processing_toolchain_status(): void
    {
        $user = $this->createSuperAdmin();
        $ebook = $this->ebook();

        $this->mock(PdfToolchain::class, function (MockInterface $mock): void {
            $mock->shouldReceive('availability')
                ->once()
                ->andReturn([
                    'pdfinfo' => '/usr/bin/pdfinfo',
                    'pdftocairo' => '/usr/bin/pdftocairo',
                ]);
        });

        $this->actingAs($user)
            ->get("/admin/ebooks/{$ebook->getKey()}/edit")
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Admin/Ebooks/Form')
                    ->where('processingConfig.available', true)
                    ->where('processingConfig.pdfinfo_available', true)
                    ->where('processingConfig.pdftocairo_available', true),
            );
    }

    public function test_ebook_index_uses_generated_preview_as_cover_fallback(): void
    {
        Storage::fake('public');

        $user = $this->createSuperAdmin();
        $ebook = $this->ebook('Preview Fallback');
        $preview = 'ebooks/generated-previews/'.$ebook->getKey().'/preview.jpg';

        Storage::disk('public')->put($preview, 'preview');

        EbookFile::query()->create([
            'ebook_id' => $ebook->getKey(),
            'source_type' => 'local',
            'disk' => 'local',
            'path' => 'ebooks/'.$ebook->getKey().'/book.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'sha256' => str_repeat('b', 64),
            'verification_status' => 'verified',
            'processing_status' => 'processed',
            'page_count' => 33,
            'pdf_metadata' => ['pages' => 33],
            'preview_path' => $preview,
            'processed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/admin/ebooks')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('ebooks.data.0.title', 'Preview Fallback')
                    ->where('ebooks.data.0.file_processing_status', 'processed')
                    ->where('ebooks.data.0.file_page_count', 33)
                    ->where('ebooks.data.0.cover_url', Storage::disk('public')->url($preview)),
            );
    }

    public function test_removing_source_also_removes_generated_preview(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = $this->createSuperAdmin();
        $ebook = $this->ebook();
        $file = $this->localSource($ebook, $this->pdfContent());

        $preview = 'ebooks/generated-previews/'.$ebook->getKey().'/preview.jpg';
        Storage::disk('public')->put($preview, 'preview');
        $file->forceFill([
            'preview_path' => $preview,
            'processing_status' => 'processed',
        ])->save();

        $this->actingAs($user)
            ->deleteJson("/admin/ebooks/{$ebook->getKey()}/storage")
            ->assertOk();

        Storage::disk('local')->assertMissing($file->path);
        Storage::disk('public')->assertMissing($preview);
        $this->assertDatabaseMissing('ebook_files', ['id' => $file->getKey()]);
    }

    private function fakeToolchain(
        int $pages,
        string $title = 'PDF Title',
        string $author = 'PDF Author',
    ): void {
        $metadata = $this->metadata($pages, $title, $author);

        $this->mock(PdfToolchain::class, function (MockInterface $mock) use ($metadata): void {
            $mock->shouldReceive('inspect')
                ->once()
                ->andReturn($metadata);
            $mock->shouldReceive('renderFirstPage')
                ->once()
                ->andReturnUsing(function (string $pdf, string $prefix): string {
                    file_put_contents($prefix.'.jpg', 'jpeg-preview');

                    return $prefix.'.jpg';
                });
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(
        int $pages,
        string $title = 'PDF Title',
        string $author = 'PDF Author',
    ): array {
        return [
            'title' => $title,
            'author' => $author,
            'subject' => 'Subject',
            'keywords' => 'one, two',
            'creator' => 'Test Creator',
            'producer' => 'Test Producer',
            'creation_date' => null,
            'modification_date' => null,
            'tagged' => false,
            'user_properties' => false,
            'suspects' => false,
            'form' => 'none',
            'javascript' => false,
            'pages' => $pages,
            'encrypted' => false,
            'page_size' => '595 x 842 pts (A4)',
            'page_rotation' => 0,
            'file_size' => null,
            'optimized' => true,
            'pdf_version' => '1.7',
        ];
    }

    private function localSource(
        Ebook $ebook,
        string $content,
        ?string $sha256 = null,
        ?string $path = null,
    ): EbookFile {
        $path ??= 'ebooks/'.$ebook->getKey().'/book.pdf';
        Storage::disk('local')->put($path, $content);

        return EbookFile::query()->create([
            'ebook_id' => $ebook->getKey(),
            'source_type' => 'local',
            'disk' => 'local',
            'path' => $path,
            'original_name' => basename($path),
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($content),
            'sha256' => $sha256 ?? hash('sha256', $content),
            'verification_status' => 'verified',
            'processing_status' => 'pending',
        ]);
    }

    private function pdfContent(string $padding = 'P'): string
    {
        return "%PDF-1.7\n".str_repeat($padding, 4096);
    }

    private function ebook(string $title = 'PDF Processing Test'): Ebook
    {
        return Ebook::query()->create([
            'title' => $title,
            'slug' => 'pdf-processing-'.fake()->unique()->uuid(),
            'publication_status' => 'draft',
            'read_enabled' => true,
            'download_enabled' => true,
        ]);
    }

    private function createSuperAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('slug', 'super-admin')->firstOrFail();

        $user->roles()->attach($role);

        return $user;
    }
}
