<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\Domain\Models\Role;
use App\Modules\Library\Application\Storage\UploadPolicy;
use App\Modules\Library\Domain\Models\Ebook;
use App\Modules\Library\Domain\Models\EbookFile;
use App\Modules\Library\Domain\Models\EbookUploadSession;
use App\Modules\Settings\Application\SettingsManager;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminEbookStorageTest extends TestCase
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

    public function test_edit_form_exposes_storage_configuration(): void
    {
        $user = $this->createSuperAdmin();
        $ebook = $this->ebook();

        $this->actingAs($user)
            ->get("/admin/ebooks/{$ebook->getKey()}/edit")
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Admin/Ebooks/Form')
                    ->where('fileSource', null)
                    ->where('uploadConfig.max_pdf_mb', 300)
                    ->where('uploadConfig.checksum_enabled', true)
                    ->where('uploadConfig.preferred_source', 'local'),
            );
    }

    public function test_chunk_upload_assembles_private_pdf_and_records_checksum(): void
    {
        Storage::fake('local');

        $this->setSmallChunks();

        $user = $this->createSuperAdmin();
        $ebook = $this->ebook();

        $chunkSize = app(UploadPolicy::class)->effectiveChunkBytes();
        $content = $this->pdfContent($chunkSize + 2048);

        $session = $this->startUpload($user, $ebook, 'library-book.pdf', strlen($content));

        $this->assertSame(2, $session['total_chunks']);

        $this->uploadAllChunks($user, $ebook, $session, $content);

        $complete = $this->actingAs($user)
            ->postJson("/admin/ebooks/{$ebook->getKey()}/uploads/{$session['id']}/complete")
            ->assertOk()
            ->json('source');

        $this->assertSame('local', $complete['source_type']);
        $this->assertSame(strlen($content), $complete['size_bytes']);
        $this->assertSame(hash('sha256', $content), $complete['sha256']);

        $file = EbookFile::query()->where('ebook_id', $ebook->getKey())->firstOrFail();

        $this->assertSame('local', $file->source_type);
        $this->assertSame('local', $file->disk);
        $this->assertSame('verified', $file->verification_status);
        $this->assertSame(hash('sha256', $content), $file->sha256);

        Storage::disk('local')->assertExists($file->path);

        $uploadSession = EbookUploadSession::query()->findOrFail($session['id']);

        $this->assertSame('completed', $uploadSession->status);
        $this->assertSame($uploadSession->total_chunks, $uploadSession->received_chunks);
        $this->assertSame(0, $uploadSession->chunks()->count());

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->getKey(),
            'event' => 'library.ebook.file.uploaded',
            'subject_type' => 'ebook',
            'subject_id' => (string) $ebook->getKey(),
        ]);
    }

    public function test_upload_session_is_resumable_and_duplicate_chunk_is_idempotent(): void
    {
        Storage::fake('local');

        $this->setSmallChunks();

        $user = $this->createSuperAdmin();
        $ebook = $this->ebook();
        $chunkSize = app(UploadPolicy::class)->effectiveChunkBytes();
        $content = $this->pdfContent($chunkSize + 100);

        $first = $this->startUpload($user, $ebook, 'resume.pdf', strlen($content));
        $second = $this->startUpload($user, $ebook, 'resume.pdf', strlen($content));

        $this->assertSame($first['id'], $second['id']);

        $firstChunk = substr($content, 0, $first['chunk_size']);
        $file = UploadedFile::fake()->createWithContent('chunk-0.part', $firstChunk);

        $this->actingAs($user)
            ->post(
                "/admin/ebooks/{$ebook->getKey()}/uploads/{$first['id']}/chunks/0",
                ['chunk' => $file],
                ['Accept' => 'application/json'],
            )
            ->assertOk()
            ->assertJsonPath('session.received_chunks', 1);

        $duplicate = UploadedFile::fake()->createWithContent('chunk-0.part', $firstChunk);

        $this->actingAs($user)
            ->post(
                "/admin/ebooks/{$ebook->getKey()}/uploads/{$first['id']}/chunks/0",
                ['chunk' => $duplicate],
                ['Accept' => 'application/json'],
            )
            ->assertOk()
            ->assertJsonPath('session.received_chunks', 1)
            ->assertJsonPath('session.received_indices.0', 0)
            ->assertJsonPath('session.received_chunks_meta.0.sha256', hash('sha256', $firstChunk));

        $this->assertDatabaseCount('ebook_upload_chunks', 1);

        $this->actingAs($user)
            ->getJson("/admin/ebooks/{$ebook->getKey()}/uploads/{$first['id']}")
            ->assertOk()
            ->assertJsonPath('session.received_chunks', 1)
            ->assertJsonPath('session.received_indices.0', 0);
    }

    public function test_client_chunk_checksum_mismatch_is_rejected_before_storage(): void
    {
        Storage::fake('local');

        $user = $this->createSuperAdmin();
        $ebook = $this->ebook();
        $content = $this->pdfContent(4096);
        $session = $this->startUpload($user, $ebook, 'chunk-checksum.pdf', strlen($content));

        $this->actingAs($user)
            ->post(
                "/admin/ebooks/{$ebook->getKey()}/uploads/{$session['id']}/chunks/0",
                [
                    'chunk' => UploadedFile::fake()->createWithContent('chunk-0.part', $content),
                    'chunk_sha256' => str_repeat('a', 64),
                ],
                ['Accept' => 'application/json'],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('chunk');

        $this->assertDatabaseCount('ebook_upload_chunks', 0);
        Storage::disk('local')->assertMissing(
            'ebook-upload-tmp/'.$session['id'].'/0.part',
        );
    }

    public function test_non_pdf_signature_is_rejected_during_final_assembly(): void
    {
        Storage::fake('local');

        $user = $this->createSuperAdmin();
        $ebook = $this->ebook();
        $content = str_repeat('N', 4096);
        $session = $this->startUpload($user, $ebook, 'not-a-pdf.pdf', strlen($content));

        $this->uploadAllChunks($user, $ebook, $session, $content);

        $this->actingAs($user)
            ->postJson("/admin/ebooks/{$ebook->getKey()}/uploads/{$session['id']}/complete")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseMissing('ebook_files', [
            'ebook_id' => $ebook->getKey(),
        ]);

        $this->assertSame(
            'uploading',
            EbookUploadSession::query()->findOrFail($session['id'])->status,
        );
    }

    public function test_expected_sha256_mismatch_is_rejected(): void
    {
        Storage::fake('local');

        $user = $this->createSuperAdmin();
        $ebook = $this->ebook();
        $content = $this->pdfContent(4096);

        $session = $this->startUpload(
            $user,
            $ebook,
            'checksum.pdf',
            strlen($content),
            str_repeat('a', 64),
        );

        $this->uploadAllChunks($user, $ebook, $session, $content);

        $this->actingAs($user)
            ->postJson("/admin/ebooks/{$ebook->getKey()}/uploads/{$session['id']}/complete")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseMissing('ebook_files', [
            'ebook_id' => $ebook->getKey(),
        ]);
    }

    public function test_replacing_local_source_removes_previous_private_file(): void
    {
        Storage::fake('local');

        $user = $this->createSuperAdmin();
        $ebook = $this->ebook();

        $firstContent = $this->pdfContent(4096, 'A');
        $firstSession = $this->startUpload($user, $ebook, 'first.pdf', strlen($firstContent));
        $this->uploadAllChunks($user, $ebook, $firstSession, $firstContent);
        $this->actingAs($user)
            ->postJson("/admin/ebooks/{$ebook->getKey()}/uploads/{$firstSession['id']}/complete")
            ->assertOk();

        $firstPath = EbookFile::query()
            ->where('ebook_id', $ebook->getKey())
            ->value('path');

        Storage::disk('local')->assertExists($firstPath);

        $secondContent = $this->pdfContent(5000, 'B');
        $secondSession = $this->startUpload($user, $ebook, 'second.pdf', strlen($secondContent));
        $this->uploadAllChunks($user, $ebook, $secondSession, $secondContent);
        $this->actingAs($user)
            ->postJson("/admin/ebooks/{$ebook->getKey()}/uploads/{$secondSession['id']}/complete")
            ->assertOk();

        $secondPath = EbookFile::query()
            ->where('ebook_id', $ebook->getKey())
            ->value('path');

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($secondPath);
    }

    public function test_verified_external_pdf_can_be_attached(): void
    {
        Http::fake([
            'https://8.8.8.8/book.pdf' => Http::sequence()
                ->push('', 200, [
                    'Content-Length' => '100',
                    'Content-Type' => 'application/pdf',
                    'ETag' => '"abc"',
                ])
                ->push('%PDF-', 206, [
                    'Content-Range' => 'bytes 0-4/100',
                    'Content-Type' => 'application/pdf',
                ]),
        ]);

        $user = $this->createSuperAdmin();
        $ebook = $this->ebook();

        $this->actingAs($user)
            ->postJson(
                "/admin/ebooks/{$ebook->getKey()}/storage/external",
                ['external_url' => 'https://8.8.8.8/book.pdf'],
            )
            ->assertOk()
            ->assertJsonPath('source.source_type', 'external_url')
            ->assertJsonPath('source.verification_status', 'verified')
            ->assertJsonPath('source.size_bytes', 100);

        $this->assertDatabaseHas('ebook_files', [
            'ebook_id' => $ebook->getKey(),
            'source_type' => 'external_url',
            'external_url' => 'https://8.8.8.8/book.pdf',
            'verification_status' => 'verified',
            'size_bytes' => 100,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->getKey(),
            'event' => 'library.ebook.file.external_attached',
            'subject_id' => (string) $ebook->getKey(),
        ]);
    }

    public function test_external_url_to_private_network_is_rejected_before_request(): void
    {
        Http::preventStrayRequests();

        $user = $this->createSuperAdmin();
        $ebook = $this->ebook();

        $this->actingAs($user)
            ->postJson(
                "/admin/ebooks/{$ebook->getKey()}/storage/external",
                ['external_url' => 'https://127.0.0.1/private.pdf'],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('external_url');

        $this->assertDatabaseMissing('ebook_files', [
            'ebook_id' => $ebook->getKey(),
        ]);

        Http::assertNothingSent();
    }

    public function test_external_redirect_to_private_network_is_rejected(): void
    {
        Http::fake([
            'https://8.8.8.8/start.pdf' => Http::response('', 302, [
                'Location' => 'https://127.0.0.1/private.pdf',
            ]),
        ]);

        $user = $this->createSuperAdmin();
        $ebook = $this->ebook();

        $this->actingAs($user)
            ->postJson(
                "/admin/ebooks/{$ebook->getKey()}/storage/external",
                ['external_url' => 'https://8.8.8.8/start.pdf'],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('external_url');

        $this->assertDatabaseMissing('ebook_files', [
            'ebook_id' => $ebook->getKey(),
        ]);

        Http::assertSentCount(1);
    }

    public function test_external_pdf_over_maximum_size_is_rejected(): void
    {
        $max = app(UploadPolicy::class)->maxPdfBytes();

        Http::fake([
            'https://8.8.8.8/huge.pdf' => Http::response('', 200, [
                'Content-Length' => (string) ($max + 1),
                'Content-Type' => 'application/pdf',
            ]),
        ]);

        $user = $this->createSuperAdmin();
        $ebook = $this->ebook();

        $this->actingAs($user)
            ->postJson(
                "/admin/ebooks/{$ebook->getKey()}/storage/external",
                ['external_url' => 'https://8.8.8.8/huge.pdf'],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('external_url');

        $this->assertDatabaseMissing('ebook_files', [
            'ebook_id' => $ebook->getKey(),
        ]);
    }

    public function test_external_verification_can_be_skipped_from_settings_without_network_request(): void
    {
        Http::preventStrayRequests();

        app(SettingsManager::class)->updateGroup(
            'storage',
            ['verify_external_urls' => false],
            null,
        );

        $user = $this->createSuperAdmin();
        $ebook = $this->ebook();

        $this->actingAs($user)
            ->postJson(
                "/admin/ebooks/{$ebook->getKey()}/storage/external",
                ['external_url' => 'https://8.8.8.8/book.pdf'],
            )
            ->assertOk()
            ->assertJsonPath('source.verification_status', 'skipped');

        Http::assertNothingSent();

        $this->assertDatabaseHas('ebook_files', [
            'ebook_id' => $ebook->getKey(),
            'verification_status' => 'skipped',
        ]);
    }

    public function test_removing_local_source_deletes_private_file(): void
    {
        Storage::fake('local');

        $user = $this->createSuperAdmin();
        $ebook = $this->ebook();

        Storage::disk('local')->put('ebooks/1/book.pdf', '%PDF-1.4');
        $file = EbookFile::query()->create([
            'ebook_id' => $ebook->getKey(),
            'source_type' => 'local',
            'disk' => 'local',
            'path' => 'ebooks/1/book.pdf',
            'original_name' => 'book.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 8,
            'sha256' => hash('sha256', '%PDF-1.4'),
            'verification_status' => 'verified',
        ]);

        Storage::disk('local')->assertExists($file->path);

        $this->actingAs($user)
            ->deleteJson("/admin/ebooks/{$ebook->getKey()}/storage")
            ->assertOk()
            ->assertJsonPath('removed', true);

        Storage::disk('local')->assertMissing('ebooks/1/book.pdf');

        $this->assertDatabaseMissing('ebook_files', [
            'id' => $file->getKey(),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->getKey(),
            'event' => 'library.ebook.file.removed',
            'subject_id' => (string) $ebook->getKey(),
        ]);
    }

    public function test_upload_size_above_configured_limit_is_rejected_without_allocating_file(): void
    {
        $user = $this->createSuperAdmin();
        $ebook = $this->ebook();
        $tooLarge = app(UploadPolicy::class)->maxPdfBytes() + 1;

        $this->actingAs($user)
            ->postJson("/admin/ebooks/{$ebook->getKey()}/uploads", [
                'file_name' => 'too-large.pdf',
                'size_bytes' => $tooLarge,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('size_bytes');

        $this->assertDatabaseCount('ebook_upload_sessions', 0);
    }

    public function test_external_verification_command_rechecks_due_sources(): void
    {
        Http::fake([
            'https://8.8.8.8/recheck.pdf' => Http::sequence()
                ->push('', 200, [
                    'Content-Length' => '222',
                    'Content-Type' => 'application/pdf',
                ])
                ->push('%PDF-', 206, [
                    'Content-Range' => 'bytes 0-4/222',
                    'Content-Type' => 'application/pdf',
                ]),
        ]);

        $ebook = $this->ebook();

        $file = EbookFile::query()->create([
            'ebook_id' => $ebook->getKey(),
            'source_type' => 'external_url',
            'external_url' => 'https://8.8.8.8/recheck.pdf',
            'verification_status' => 'failed',
            'last_checked_at' => now()->subHours(25),
            'last_error' => 'old failure',
        ]);

        $this->artisan('ebooks:external:verify')
            ->expectsOutput('Checked 1 external source(s): 1 verified, 0 failed.')
            ->assertSuccessful();

        $file->refresh();

        $this->assertSame('verified', $file->verification_status);
        $this->assertSame(222, $file->size_bytes);
        $this->assertNull($file->last_error);
        $this->assertNotNull($file->verified_at);
    }

    public function test_cleanup_command_removes_expired_session_and_temporary_chunks(): void
    {
        Storage::fake('local');

        $user = $this->createSuperAdmin();
        $ebook = $this->ebook();
        $sessionId = '11111111-1111-4111-8111-111111111111';
        $tempDirectory = 'ebook-upload-tmp/'.$sessionId;

        $session = EbookUploadSession::query()->create([
            'id' => $sessionId,
            'ebook_id' => $ebook->getKey(),
            'user_id' => $user->getKey(),
            'original_name' => 'expired.pdf',
            'total_size' => 4096,
            'chunk_size' => 4096,
            'total_chunks' => 1,
            'received_chunks' => 1,
            'status' => 'uploading',
            'temp_directory' => $tempDirectory,
            'expires_at' => now()->subMinute(),
        ]);

        Storage::disk('local')->put($tempDirectory.'/0.part', '%PDF-1.7');

        $session->chunks()->create([
            'chunk_index' => 0,
            'size_bytes' => 8,
            'sha256' => hash('sha256', '%PDF-1.7'),
        ]);

        $this->artisan('ebooks:uploads:cleanup')
            ->expectsOutput('Cleaned 1 expired ebook upload session(s).')
            ->assertSuccessful();

        $this->assertDatabaseMissing('ebook_upload_sessions', ['id' => $sessionId]);
        $this->assertDatabaseCount('ebook_upload_chunks', 0);
        Storage::disk('local')->assertMissing($tempDirectory.'/0.part');
    }

    public function test_upload_session_is_scoped_to_the_admin_who_created_it(): void
    {
        Storage::fake('local');

        $firstAdmin = $this->createSuperAdmin();
        $secondAdmin = $this->createSuperAdmin();
        $ebook = $this->ebook();
        $content = $this->pdfContent(4096);

        $session = $this->startUpload(
            $firstAdmin,
            $ebook,
            'owned.pdf',
            strlen($content),
        );

        $this->actingAs($secondAdmin)
            ->getJson("/admin/ebooks/{$ebook->getKey()}/uploads/{$session['id']}")
            ->assertNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    private function startUpload(
        User $user,
        Ebook $ebook,
        string $fileName,
        int $size,
        ?string $sha256 = null,
    ): array {
        $payload = [
            'file_name' => $fileName,
            'size_bytes' => $size,
        ];

        if ($sha256 !== null) {
            $payload['sha256'] = $sha256;
        }

        return $this->actingAs($user)
            ->postJson("/admin/ebooks/{$ebook->getKey()}/uploads", $payload)
            ->assertOk()
            ->json('session');
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function uploadAllChunks(
        User $user,
        Ebook $ebook,
        array $session,
        string $content,
    ): void {
        for ($index = 0; $index < $session['total_chunks']; $index++) {
            $offset = $index * $session['chunk_size'];
            $chunkContent = substr($content, $offset, $session['chunk_size']);

            $this->actingAs($user)
                ->post(
                    "/admin/ebooks/{$ebook->getKey()}/uploads/{$session['id']}/chunks/{$index}",
                    [
                        'chunk' => UploadedFile::fake()->createWithContent(
                            "chunk-{$index}.part",
                            $chunkContent,
                        ),
                    ],
                    ['Accept' => 'application/json'],
                )
                ->assertOk();
        }
    }

    private function pdfContent(int $size, string $padding = 'P'): string
    {
        $prefix = "%PDF-1.7\n";

        return $prefix.str_repeat($padding, max(0, $size - strlen($prefix)));
    }

    private function setSmallChunks(): void
    {
        app(SettingsManager::class)->updateGroup(
            'uploads',
            [
                'max_pdf_mb' => 10,
                'chunk_size_mb' => 2,
                'checksum_enabled' => true,
            ],
            null,
        );
    }

    private function ebook(): Ebook
    {
        return Ebook::query()->create([
            'title' => 'Storage Test Ebook '.fake()->unique()->word(),
            'slug' => 'storage-test-'.fake()->unique()->uuid(),
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
