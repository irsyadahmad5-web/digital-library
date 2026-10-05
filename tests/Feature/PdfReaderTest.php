<?php

namespace Tests\Feature;

use App\Modules\Library\Domain\Models\Ebook;
use App\Modules\Library\Domain\Models\EbookFile;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PdfReaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        Storage::fake('local');
    }

    public function test_readable_public_book_opens_reader_without_exposing_storage_path(): void
    {
        [$ebook, $file] = $this->localBook('Reader Book');

        $this->get('/read/'.$ebook->slug)
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Reader/Index')
                    ->where('book.title', 'Reader Book')
                    ->where('book.slug', $ebook->slug)
                    ->where('book.revision', $file->readerRevision())
                    ->where('sourceUrl', route('reader.source', ['slug' => $ebook->slug]))
                    ->where('backUrl', route('books.show', ['slug' => $ebook->slug]))
                    ->missing('path')
                    ->missing('external_url'),
            );
    }

    public function test_reader_revision_tracks_content_not_routine_metadata_updates(): void
    {
        [, $file] = $this->localBook('Stable Reader Revision');

        $initial = $file->readerRevision();

        $file->forceFill([
            'last_checked_at' => now(),
            'last_error' => null,
        ])->save();

        $this->assertSame($initial, $file->fresh()->readerRevision());

        $file->forceFill([
            'sha256' => hash('sha256', 'replacement-pdf-content'),
        ])->save();

        $this->assertNotSame($initial, $file->fresh()->readerRevision());
    }

    public function test_read_disabled_or_non_public_book_cannot_open_reader_source(): void
    {
        [$disabled] = $this->localBook(
            'Disabled Reader',
            ['read_enabled' => false],
        );
        [$draft] = $this->localBook(
            'Draft Reader',
            [
                'publication_status' => 'draft',
                'published_at' => null,
            ],
        );

        $this->get('/read/'.$disabled->slug)->assertNotFound();
        $this->get('/read/'.$disabled->slug.'/file')->assertNotFound();
        $this->get('/read/'.$draft->slug)->assertNotFound();
        $this->get('/read/'.$draft->slug.'/file')->assertNotFound();
    }

    public function test_local_pdf_full_stream_has_reader_headers(): void
    {
        [$ebook, $file, $pdf] = $this->localBook('Full Stream');

        $response = $this->get('/read/'.$ebook->slug.'/file');

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Length', (string) strlen($pdf))
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');

        $this->assertSame($pdf, $response->streamedContent());
        $this->assertStringContainsString(
            (string) $file->sha256,
            (string) $response->headers->get('ETag'),
        );
    }

    public function test_local_pdf_supports_partial_range_and_suffix_range(): void
    {
        [$ebook, , $pdf] = $this->localBook('Range Stream');

        $first = $this->get(
            '/read/'.$ebook->slug.'/file',
            ['Range' => 'bytes=0-4'],
        );

        $first
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-4/'.strlen($pdf))
            ->assertHeader('Content-Length', '5');

        $this->assertSame('%PDF-', $first->streamedContent());

        $suffix = $this->get(
            '/read/'.$ebook->slug.'/file',
            ['Range' => 'bytes=-5'],
        );

        $suffix
            ->assertStatus(206)
            ->assertHeader(
                'Content-Range',
                'bytes '.(strlen($pdf) - 5).'-'.(strlen($pdf) - 1).'/'.strlen($pdf),
            )
            ->assertHeader('Content-Length', '5');

        $this->assertSame(substr($pdf, -5), $suffix->streamedContent());
    }

    public function test_invalid_or_out_of_bounds_range_returns_416(): void
    {
        [$ebook, , $pdf] = $this->localBook('Invalid Range');

        foreach ([
            'bytes=999999-',
            'bytes=10-5',
            'bytes=0-1,3-4',
            'items=0-4',
        ] as $range) {
            $this->get(
                '/read/'.$ebook->slug.'/file',
                ['Range' => $range],
            )
                ->assertStatus(416)
                ->assertHeader('Content-Range', 'bytes */'.strlen($pdf));
        }
    }

    public function test_head_request_returns_metadata_without_body(): void
    {
        [$ebook, , $pdf] = $this->localBook('Head Stream');

        $response = $this->call(
            'HEAD',
            '/read/'.$ebook->slug.'/file',
            server: ['HTTP_RANGE' => 'bytes=0-9'],
        );

        $response
            ->assertStatus(206)
            ->assertHeader('Content-Length', '10')
            ->assertHeader('Content-Range', 'bytes 0-9/'.strlen($pdf))
            ->assertHeader('Accept-Ranges', 'bytes');

        $this->assertSame('', $response->getContent());
    }

    public function test_external_pdf_proxy_forwards_range_and_hides_external_url(): void
    {
        [$ebook] = $this->externalBook(
            'External Reader',
            'https://example.com/books/reader.pdf',
            100,
        );

        Http::fake([
            'https://example.com/books/reader.pdf' => Http::response(
                '%PDF-',
                206,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Length' => '5',
                    'Content-Range' => 'bytes 0-4/100',
                    'Accept-Ranges' => 'bytes',
                    'ETag' => '"external-etag"',
                ],
            ),
        ]);

        $response = $this->get(
            '/read/'.$ebook->slug.'/file',
            ['Range' => 'bytes=0-4'],
        );

        $response
            ->assertStatus(206)
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Length', '5')
            ->assertHeader('Content-Range', 'bytes 0-4/100');

        $this->assertSame('%PDF-', $response->streamedContent());

        Http::assertSent(
            fn ($request): bool => $request->url() === 'https://example.com/books/reader.pdf'
                && $request->hasHeader('Range', 'bytes=0-4'),
        );

        $this->get('/read/'.$ebook->slug)
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('sourceUrl', route('reader.source', ['slug' => $ebook->slug]))
                    ->missing('external_url'),
            );
    }

    public function test_external_redirect_to_private_network_is_blocked(): void
    {
        [$ebook] = $this->externalBook(
            'Redirect Private External',
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

        $this->get('/read/'.$ebook->slug.'/file')
            ->assertStatus(502);

        Http::assertSentCount(1);
        Http::assertSent(
            fn ($request): bool => $request->url() === 'https://example.com/redirect.pdf',
        );
    }

    public function test_external_reader_blocks_private_network_before_http_request(): void
    {
        [$ebook] = $this->externalBook(
            'Private External',
            'https://127.0.0.1/private.pdf',
            100,
            'skipped',
        );

        Http::fake();

        $this->get('/read/'.$ebook->slug.'/file')
            ->assertStatus(502);

        Http::assertNothingSent();
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

        $pdf = "%PDF-1.4\nreader-stage-13\n%%EOF";
        $path = 'ebooks/'.$ebook->getKey().'/reader.pdf';

        Storage::disk('local')->put($path, $pdf);

        $file = EbookFile::query()->create([
            'ebook_id' => $ebook->getKey(),
            'source_type' => 'local',
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'reader.pdf',
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
        string $verificationStatus = 'verified',
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
            'verification_status' => $verificationStatus,
            'processing_status' => 'processed',
            'page_count' => 2,
            'processed_at' => now(),
        ]);

        return [$ebook, $file];
    }
}
