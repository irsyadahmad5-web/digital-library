<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Audit\Application\AuditLogger;
use App\Modules\Identity\Application\SessionRevoker;
use App\Modules\Identity\Domain\Models\Role;
use App\Modules\Library\Application\EbookCoverManager;
use App\Modules\Library\Application\Storage\EbookFileManager;
use App\Modules\Library\Application\Storage\ExternalUrlGuard;
use App\Modules\Library\Domain\Models\Ebook;
use App\Modules\Library\Domain\Models\EbookFile;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\SettingsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_html_responses_send_strict_security_headers_with_matching_csp_nonce(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader(
                'Referrer-Policy',
                'strict-origin-when-cross-origin',
            )
            ->assertHeader(
                'Cross-Origin-Opener-Policy',
                'same-origin',
            );

        $csp = (string) $response->headers->get(
            'Content-Security-Policy',
        );

        $this->assertStringContainsString(
            "default-src 'self'",
            $csp,
        );
        $this->assertStringContainsString(
            "object-src 'none'",
            $csp,
        );
        $this->assertStringContainsString(
            "frame-ancestors 'none'",
            $csp,
        );
        $this->assertStringContainsString(
            "script-src-attr 'none'",
            $csp,
        );
        $this->assertStringNotContainsString(
            "'unsafe-eval'",
            $csp,
        );

        preg_match(
            "/script-src[^;]*'nonce-([^']+)'/",
            $csp,
            $matches,
        );

        $nonce = $matches[1] ?? '';

        $this->assertNotSame('', $nonce);
        $this->assertStringContainsString(
            'nonce="'.$nonce.'"',
            (string) $response->getContent(),
        );
    }

    public function test_hsts_is_sent_only_for_secure_requests_when_enabled(): void
    {
        config([
            'security.hsts_enabled' => true,
            'security.hsts_max_age' => 31536000,
            'security.hsts_include_subdomains' => true,
            'security.hsts_preload' => false,
        ]);

        $this->get('/')
            ->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/')
            ->assertOk()
            ->assertHeader(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
    }

    public function test_host_allowlist_rejects_untrusted_host_and_accepts_app_url_host(): void
    {
        config([
            'security.enforce_host' => true,
            'security.allowed_hosts' => [],
            'app.url' => 'https://library.example.test',
        ]);

        $this->get('http://evil.example.test/')
            ->assertStatus(400);

        $this->get('https://library.example.test/')
            ->assertOk();
    }

    public function test_forced_password_change_blocks_admin_features_but_allows_profile(): void
    {
        $user = $this->createSuperAdmin([
            'force_password_change' => true,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect('/admin/profile');

        $this->actingAs($user)
            ->get('/admin/profile')
            ->assertOk();

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->get('/admin/analytics')
            ->assertStatus(423)
            ->assertJsonPath(
                'message',
                'Password wajib diperbarui sebelum melanjutkan.',
            );
    }

    public function test_security_sensitive_routes_keep_web_rate_limit_and_password_change_middleware(): void
    {
        $login = Route::getRoutes()->getByName('admin.login.store');
        $chunk = Route::getRoutes()->getByName(
            'admin.ebooks.uploads.chunk',
        );
        $dashboard = Route::getRoutes()->getByName('admin.dashboard');
        $search = Route::getRoutes()->getByName('library.search');

        $this->assertNotNull($login);
        $this->assertNotNull($chunk);
        $this->assertNotNull($dashboard);
        $this->assertNotNull($search);

        $this->assertContains(
            'web',
            $login->gatherMiddleware(),
        );
        $this->assertContains(
            'throttle:20,1',
            $login->gatherMiddleware(),
        );
        $this->assertContains(
            'throttle:240,1',
            $chunk->gatherMiddleware(),
        );
        $this->assertContains(
            'password.changed',
            $dashboard->gatherMiddleware(),
        );
        $this->assertContains(
            'throttle:120,1',
            $search->gatherMiddleware(),
        );
    }

    public function test_audit_log_redacts_secrets_and_bounds_untrusted_metadata(): void
    {
        $request = Request::create(
            '/admin/security-probe',
            'POST',
            server: [
                'REMOTE_ADDR' => '203.0.113.10',
                'HTTP_USER_AGENT' => str_repeat('A', 1500),
            ],
        );

        $log = app(AuditLogger::class)->log(
            'security.probe',
            metadata: [
                'password' => 'super-secret',
                'nested' => [
                    'token' => 'reset-secret',
                    'safe' => str_repeat('B', 5000),
                ],
                'object' => (object) ['secret' => 'hidden'],
            ],
            request: $request,
        );

        $this->assertSame(
            '[REDACTED]',
            $log->metadata['password'],
        );
        $this->assertSame(
            '[REDACTED]',
            $log->metadata['nested']['token'],
        );
        $this->assertSame(
            4000,
            strlen($log->metadata['nested']['safe']),
        );
        $this->assertSame(
            '[UNSUPPORTED]',
            $log->metadata['object'],
        );
        $this->assertSame(1000, strlen((string) $log->user_agent));
    }

    public function test_external_url_guard_rejects_ssrf_bypass_shapes_before_request(): void
    {
        $guard = app(ExternalUrlGuard::class);

        foreach ([
            'https://127.1/private.pdf',
            'https://[::1]/private.pdf',
            'https://user:password@example.com/book.pdf',
            'https://example.com:8443/book.pdf',
            'https://localhost./book.pdf',
            'https://example.com%00.evil.test/book.pdf',
        ] as $url) {
            try {
                $guard->assertAllowed($url, true);
                $this->fail('SSRF bypass shape was accepted: '.$url);
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_external_hostname_requests_are_dns_pinned_after_public_ip_validation(): void
    {
        $target = app(ExternalUrlGuard::class)->requestOptions(
            'https://example.com/book.pdf',
            true,
        );

        $this->assertSame(
            'https://example.com/book.pdf',
            $target['url'],
        );
        $this->assertArrayHasKey('curl', $target['options']);
        $this->assertArrayHasKey(
            CURLOPT_RESOLVE,
            $target['options']['curl'],
        );

        $resolve = $target['options']['curl'][CURLOPT_RESOLVE];

        $this->assertIsArray($resolve);
        $this->assertNotEmpty($resolve);
        $this->assertStringStartsWith(
            'example.com:443:',
            (string) $resolve[0],
        );
    }

    public function test_tampered_private_ebook_path_cannot_escape_storage_scope(): void
    {
        $ebook = Ebook::query()->create([
            'title' => 'Traversal Probe',
            'slug' => 'traversal-probe',
            'publication_status' => 'published',
            'read_enabled' => true,
            'download_enabled' => true,
            'published_at' => now(),
            'page_count' => 1,
        ]);

        EbookFile::query()->create([
            'ebook_id' => $ebook->getKey(),
            'source_type' => 'local',
            'disk' => 'local',
            'path' => '../../etc/passwd',
            'original_name' => 'book.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'sha256' => hash('sha256', 'tampered'),
            'verification_status' => 'verified',
            'processing_status' => 'processed',
            'page_count' => 1,
            'processed_at' => now(),
        ]);

        $this->get('/read/'.$ebook->slug.'/file')
            ->assertNotFound();
    }

    public function test_tampered_preview_path_is_not_exposed_as_public_url(): void
    {
        $ebook = Ebook::query()->create([
            'title' => 'Preview Probe',
            'slug' => 'preview-probe',
            'publication_status' => 'published',
            'read_enabled' => true,
            'download_enabled' => true,
            'published_at' => now(),
        ]);

        $file = EbookFile::query()->create([
            'ebook_id' => $ebook->getKey(),
            'source_type' => 'local',
            'disk' => 'local',
            'path' => 'ebooks/'.$ebook->getKey().'/book.pdf',
            'original_name' => 'book.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'sha256' => hash('sha256', 'preview-probe'),
            'verification_status' => 'verified',
            'processing_status' => 'processed',
            'preview_path' => '../private/preview.jpg',
            'processed_at' => now(),
        ]);

        $this->assertNull(
            app(EbookFileManager::class)->previewUrl($file),
        );
    }

    public function test_tampered_cover_path_is_never_exposed_or_deleted_outside_cover_scope(): void
    {
        Storage::disk('public')->put('sensitive.txt', 'do-not-delete');

        $manager = app(EbookCoverManager::class);

        $this->assertNull($manager->url('../sensitive.txt'));

        $manager->remove('../sensitive.txt');

        Storage::disk('public')->assertExists('sensitive.txt');
    }

    public function test_search_payload_is_escaped_and_sql_injection_shape_does_not_bypass_visibility(): void
    {
        Ebook::query()->create([
            'title' => 'Published Security Book',
            'slug' => 'published-security-book',
            'publication_status' => 'published',
            'read_enabled' => true,
            'download_enabled' => true,
            'published_at' => now(),
        ]);

        Ebook::query()->create([
            'title' => 'Hidden Draft Secret',
            'slug' => 'hidden-draft-secret',
            'publication_status' => 'draft',
            'read_enabled' => true,
            'download_enabled' => true,
            'published_at' => null,
        ]);

        $payload = "<script>alert('xss')</script>";
        $response = $this->get('/library?q='.urlencode($payload));

        $response
            ->assertOk()
            ->assertDontSee($payload, false)
            ->assertDontSee('Hidden Draft Secret', false);

        $this->get('/library?q='.urlencode("' OR 1=1 --"))
            ->assertOk()
            ->assertDontSee('Hidden Draft Secret', false);

        $this->assertDatabaseCount('ebooks', 2);
    }

    public function test_session_revoker_removes_all_database_sessions_for_user(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->create();

        DB::table('sessions')->insert([
            [
                'id' => 'session-one',
                'user_id' => $user->getKey(),
                'ip_address' => '203.0.113.10',
                'user_agent' => 'Browser A',
                'payload' => 'payload',
                'last_activity' => now()->timestamp,
            ],
            [
                'id' => 'session-two',
                'user_id' => $user->getKey(),
                'ip_address' => '203.0.113.11',
                'user_agent' => 'Browser B',
                'payload' => 'payload',
                'last_activity' => now()->timestamp,
            ],
        ]);

        $deleted = app(SessionRevoker::class)->revokeAll($user);

        $this->assertSame(2, $deleted);
        $this->assertDatabaseMissing('sessions', [
            'user_id' => $user->getKey(),
        ]);
    }

    public function test_invalid_public_slug_is_rejected_before_catalog_lookup(): void
    {
        $this->get('/book/bad_slug')
            ->assertNotFound();

        $this->get('/read/bad_slug')
            ->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createSuperAdmin(array $attributes = []): User
    {
        $this->seed(AccessControlSeeder::class);

        $user = User::factory()->create($attributes);
        $role = Role::query()
            ->where('slug', 'super-admin')
            ->firstOrFail();

        $user->roles()->attach($role);

        return $user;
    }
}
