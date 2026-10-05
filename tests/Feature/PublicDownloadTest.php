<?php

namespace Tests\Feature;

use App\Modules\Library\Domain\Models\Ebook;
use App\Modules\Library\Domain\Models\EbookDownloadStat;
use App\Modules\Library\Domain\Models\EbookFile;
use App\Modules\Settings\Application\SettingsManager;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        Storage::fake('local');
    }

    public function test_local_pdf_download_streams_private_file_as_attachment(): void
    {
        [$ebook, $file, $pdf] = $this->localBook('Panduan Digital');

        $response = $this->get('/book/'.$ebook->slug.'/download');

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Length', (string) strlen($pdf))
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');

        $disposition = (string) $response->headers->get('Content-Disposition');

        $this->assertStringStartsWith('attachment;', $disposition);
        $this->assertStringContainsString('Panduan Digital.pdf', $disposition);
        $this->assertStringNotContainsString((string) $file->path, $disposition);
        $this->assertSame($pdf, $response->streamedContent());
    }

    public function test_download_access_is_independent_from_reader_access(): void
    {
        [$downloadOnly] = $this->localBook(
            'Download Only',
            ['read_enabled' => false, 'download_enabled' => true],
        );
        [$readOnly] = $this->localBook(
            'Read Only',
            ['read_enabled' => true, 'download_enabled' => false],
        );

        $this->get('/book/'.$downloadOnly->slug.'/download')->assertOk();
        $this->get('/read/'.$downloadOnly->slug)->assertNotFound();

        $this->get('/book/'.$readOnly->slug.'/download')->assertNotFound();
        $this->get('/read/'.$readOnly->slug)->assertOk();
    }

    public function test_global_download_policy_blocks_route_but_button_visibility_does_not(): void
    {
        [$ebook] = $this->localBook('Policy Book');

        $this->downloadsSettings(
            publicEnabled: true,
            trackDownloads: false,
            showDownloadButton: false,
        );

        $this->get('/book/'.$ebook->slug.'/download')->assertOk();

        $this->downloadsSettings(
            publicEnabled: false,
            trackDownloads: false,
            showDownloadButton: true,
        );

        $this->get('/book/'.$ebook->slug.'/download')->assertNotFound();
    }

    public function test_non_public_book_cannot_be_downloaded(): void
    {
        [$draft] = $this->localBook(
            'Draft Download',
            [
                'publication_status' => 'draft',
                'published_at' => null,
            ],
        );

        $this->get('/book/'.$draft->slug.'/download')->assertNotFound();
    }

    public function test_head_and_range_downloads_are_supported(): void
    {
        [$ebook, , $pdf] = $this->localBook('Resumable Download');

        $head = $this->call(
            'HEAD',
            '/book/'.$ebook->slug.'/download',
            server: ['HTTP_RANGE' => 'bytes=0-9'],
        );

        $head
            ->assertStatus(206)
            ->assertHeader('Content-Length', '10')
            ->assertHeader('Content-Range', 'bytes 0-9/'.strlen($pdf));

        $this->assertSame('', $head->getContent());

        $partial = $this->get(
            '/book/'.$ebook->slug.'/download',
            ['Range' => 'bytes=5-12'],
        );

        $partial
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 5-12/'.strlen($pdf))
            ->assertHeader('Content-Length', '8');

        $this->assertSame(substr($pdf, 5, 8), $partial->streamedContent());
    }

    public function test_download_tracking_counts_initial_gets_only_when_enabled(): void
    {
        [$ebook, , $pdf] = $this->localBook('Tracked Download');

        $this->downloadsSettings(
            publicEnabled: true,
            trackDownloads: true,
            showDownloadButton: true,
        );

        $this->get('/book/'.$ebook->slug.'/download')
            ->assertOk()
            ->streamedContent();

        $this->assertDatabaseHas('ebook_download_stats', [
            'ebook_id' => $ebook->getKey(),
            'downloads' => 1,
        ]);

        $this->call('HEAD', '/book/'.$ebook->slug.'/download')
            ->assertOk();

        $this->get(
            '/book/'.$ebook->slug.'/download',
            ['Range' => 'bytes=5-'],
        )
            ->assertStatus(206)
            ->streamedContent();

        $this->assertSame(
            1,
            EbookDownloadStat::query()
                ->where('ebook_id', $ebook->getKey())
                ->value('downloads'),
        );

        $this->get(
            '/book/'.$ebook->slug.'/download',
            ['Range' => 'bytes=0-4'],
        )
            ->assertStatus(206)
            ->streamedContent();

        $this->assertSame(
            2,
            EbookDownloadStat::query()
                ->where('ebook_id', $ebook->getKey())
                ->value('downloads'),
        );
        $this->assertNotNull(
            EbookDownloadStat::query()
                ->where('ebook_id', $ebook->getKey())
                ->value('last_downloaded_at'),
        );

        $this->assertSame('%PDF-', substr($pdf, 0, 5));
    }

    public function test_tracking_disabled_keeps_download_stats_empty(): void
    {
        [$ebook] = $this->localBook('Untracked Download');

        $this->downloadsSettings(
            publicEnabled: true,
            trackDownloads: false,
            showDownloadButton: true,
        );

        $this->get('/book/'.$ebook->slug.'/download')
            ->assertOk()
            ->streamedContent();

        $this->assertDatabaseMissing('ebook_download_stats', [
            'ebook_id' => $ebook->getKey(),
        ]);
    }

    public function test_external_download_is_proxied_without_exposing_source_url(): void
    {
        [$ebook] = $this->externalBook(
            'External Download',
            'https://example.com/library/external.pdf',
            100,
        );

        Http::fake([
            'https://example.com/library/external.pdf' => Http::response(
                '%PDF-external',
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Length' => '13',
                    'Accept-Ranges' => 'bytes',
                    'ETag' => '"download-etag"',
                ],
            ),
        ]);

        $response = $this->get('/book/'.$ebook->slug.'/download');

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith(
            'attachment;',
            (string) $response->headers->get('Content-Disposition'),
        );
        $this->assertSame('%PDF-external', $response->streamedContent());

        Http::assertSent(
            fn ($request): bool => $request->url()
                === 'https://example.com/library/external.pdf',
        );

        $this->get('/book/'.$ebook->slug)
            ->assertSee('External Download')
            ->assertDontSee('https://example.com/library/external.pdf');
    }

    public function test_external_download_blocks_private_network_redirect(): void
    {
        [$ebook] = $this->externalBook(
            'External Private Redirect',
            'https://example.com/redirect.pdf',
            100,
        );

        Http::fake([
            'https://example.com/redirect.pdf' => Http::response(
                '',
                302,
                ['Location' => 'https://127.0.0.1/private.pdf'],
            ),
        ]);

        $this->get('/book/'.$ebook->slug.'/download')
            ->assertStatus(502);

        Http::assertSentCount(1);
    }

    private function downloadsSettings(
        bool $publicEnabled,
        bool $trackDownloads,
        bool $showDownloadButton,
    ): void {
        app(SettingsManager::class)->updateGroup(
            'downloads',
            [
                'public_enabled' => $publicEnabled,
                'track_downloads' => $trackDownloads,
                'show_download_button' => $showDownloadButton,
            ],
            null,
        );
    }

    /**
     * @return array{0: Ebook, 1: EbookFile, 2: string}
     */
    private function localBook(string $title, array $ebookOverrides = []): array
    {
        $ebook = Ebook::query()->create([
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'publication_status' => 'published',
            'read_enabled' => true,
            'download_enabled' => true,
            'published_at' => now(),
            'page_count' => 2,
            ...$ebookOverrides,
        ]);

        $pdf = "%PDF-1.4\ndownload-stage-16\n%%EOF";
        $path = 'ebooks/'.$ebook->getKey().'/private-download.pdf';

        Storage::disk('local')->put($path, $pdf);

        $file = EbookFile::query()->create([
            'ebook_id' => $ebook->getKey(),
            'source_type' => 'local',
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'private-download.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($pdf),
            'sha256' => hash('sha256', $pdf),
            'verification_status' => 'verified',
            'processing_status' => 'processed',
            'page_count' => 2,
            'processed_at' => now(),
        ]);

        return [$ebook, $file, $pdf];
    }

    /**
     * @return array{0: Ebook, 1: EbookFile}
     */
    private function externalBook(
        string $title,
        string $url,
        int $size,
    ): array {
        $ebook = Ebook::query()->create([
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'publication_status' => 'published',
            'read_enabled' => true,
            'download_enabled' => true,
            'published_at' => now(),
            'page_count' => 2,
        ]);

        $file = EbookFile::query()->create([
            'ebook_id' => $ebook->getKey(),
            'source_type' => 'external_url',
            'external_url' => $url,
            'mime_type' => 'application/pdf',
            'size_bytes' => $size,
            'verification_status' => 'verified',
            'processing_status' => 'processed',
            'page_count' => 2,
            'processed_at' => now(),
        ]);

        return [$ebook, $file];
    }
}
