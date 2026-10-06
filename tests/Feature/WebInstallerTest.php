<?php

namespace Tests\Feature;

use App\Modules\Installer\Application\InstallerEnvironmentWriter;
use App\Modules\Installer\Application\InstallerRuntime;
use App\Modules\Installer\Application\InstallerState;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WebInstallerTest extends TestCase
{
    private string $sandbox;

    /**
     * @var array<string, mixed>
     */
    private array $originalConfig = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = storage_path(
            'framework/testing-installer-'.bin2hex(random_bytes(6)),
        );

        File::ensureDirectoryExists($this->sandbox);

        foreach ([
            'installer.lock_path',
            'installer.pending_path',
            'installer.bootstrap_key_path',
            'installer.env_path',
            'installer.env_backup_path',
            'installer.force_uninstalled',
            'installer.force_installed',
            'app.env',
            'app.key',
            'session.driver',
            'cache.default',
            'queue.default',
        ] as $key) {
            $this->originalConfig[$key] = config($key);
        }

        config([
            'installer.lock_path' => $this->sandbox.'/installed.lock',
            'installer.pending_path' => $this->sandbox.'/pending.json',
            'installer.bootstrap_key_path' => $this->sandbox.'/installer.key',
            'installer.env_path' => $this->sandbox.'/.env',
            'installer.env_backup_path' => $this->sandbox.'/.env.backup',
            'installer.force_uninstalled' => false,
            'installer.force_installed' => false,
        ]);
    }

    protected function tearDown(): void
    {
        config($this->originalConfig);

        File::deleteDirectory($this->sandbox);

        parent::tearDown();
    }

    public function test_uninstalled_application_redirects_normal_routes_to_installer(): void
    {
        config(['installer.force_uninstalled' => true]);

        $this->get('/')
            ->assertRedirect(route('install.index'));
    }

    public function test_installer_page_is_available_with_noindex_and_no_store_headers(): void
    {
        config(['installer.force_uninstalled' => true]);

        $response = $this->get('/install');

        $response
            ->assertOk()
            ->assertSee('Secure Web Installer', false)
            ->assertSeeText('Requirement server & koneksi database')
            ->assertHeader(
                'X-Robots-Tag',
                'noindex, nofollow, noarchive',
            );

        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );
    }

    public function test_installer_routes_are_locked_after_installation(): void
    {
        config(['installer.force_installed' => true]);

        $this->get('/install')
            ->assertRedirect(route('home'));
    }

    public function test_installer_routes_have_rate_limits_and_open_guard(): void
    {
        $database = Route::getRoutes()
            ->getByName('install.database');
        $complete = Route::getRoutes()
            ->getByName('install.complete');

        $this->assertNotNull($database);
        $this->assertNotNull($complete);

        $this->assertContains(
            'install.open',
            $database->gatherMiddleware(),
        );
        $this->assertContains(
            'throttle:10,1',
            $database->gatherMiddleware(),
        );
        $this->assertContains(
            'throttle:5,1',
            $complete->gatherMiddleware(),
        );
    }

    public function test_database_form_rejects_unsupported_driver_and_unsafe_host_before_connecting(): void
    {
        config(['installer.force_uninstalled' => true]);

        $response = $this->from('/install')->post(
            '/install/database',
            [
                'app_name' => 'Library Test',
                'app_url' => 'https://library.example.test',
                'timezone' => 'Asia/Jakarta',
                'db_connection' => 'sqlite',
                'db_host' => '127.0.0.1;unix_socket=/tmp/mysql.sock',
                'db_port' => 3306,
                'db_database' => 'library_test',
                'db_username' => 'library',
                'db_password' => 'secret',
                'trusted_proxies' => '*',
            ],
        );

        $response
            ->assertRedirect('/install')
            ->assertSessionHasErrors([
                'db_connection',
                'db_host',
                'trusted_proxies',
            ]);
    }

    public function test_environment_writer_persists_production_safe_values_atomically(): void
    {
        file_put_contents(
            $this->sandbox.'/.env',
            "APP_NAME=\"Old\"\n"
            ."APP_ENV=local\n"
            ."APP_KEY=\n"
            ."APP_DEBUG=true\n"
            ."APP_URL=http://localhost\n"
            ."DB_CONNECTION=mysql\n"
            ."DB_HOST=127.0.0.1\n"
            ."DB_PORT=3306\n"
            ."DB_DATABASE=old\n"
            ."DB_USERNAME=root\n"
            ."DB_PASSWORD=\n",
        );

        $key = 'base64:'.base64_encode(str_repeat('k', 32));

        app(InstallerEnvironmentWriter::class)->write([
            'app_name' => 'Perpustakaan Uji',
            'app_url' => 'https://library.example.test',
            'timezone' => 'Asia/Jakarta',
            'db_connection' => 'mariadb',
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_database' => 'library_test',
            'db_username' => 'library_user',
            'db_password' => 'p@ss"$word',
            'trusted_proxies' => '127.0.0.1,10.0.0.0/24',
        ], $key);

        $contents = (string) file_get_contents(
            $this->sandbox.'/.env',
        );

        $this->assertStringContainsString(
            'APP_ENV=production',
            $contents,
        );
        $this->assertStringContainsString(
            'APP_DEBUG=false',
            $contents,
        );
        $this->assertStringContainsString(
            'APP_KEY="'.$key.'"',
            $contents,
        );
        $this->assertStringContainsString(
            'DB_CONNECTION=mariadb',
            $contents,
        );
        $this->assertStringContainsString(
            'SESSION_SECURE_COOKIE=true',
            $contents,
        );
        $this->assertStringContainsString(
            'SECURITY_ALLOWED_HOSTS=library.example.test',
            $contents,
        );
        $this->assertStringContainsString(
            'SECURITY_HSTS_ENABLED=true',
            $contents,
        );
        $this->assertStringNotContainsString(
            'APP_DEBUG=true',
            $contents,
        );

        $parsed = Dotenv::parse($contents);

        $this->assertSame('p@ss"$word', $parsed['DB_PASSWORD']);
        $this->assertSame($key, $parsed['APP_KEY']);
        $this->assertFileExists($this->sandbox.'/.env.backup');
    }

    public function test_pending_state_keeps_installer_open_until_lock_is_written(): void
    {
        config(['app.env' => 'production']);

        $key = 'base64:'.base64_encode(str_repeat('x', 32));

        file_put_contents(
            $this->sandbox.'/.env',
            "APP_KEY={$key}\n",
        );

        $state = app(InstallerState::class);

        $this->assertTrue($state->isInstalled());

        $state->markPending([
            'app_name' => 'Pending Library',
            'db_database' => 'library',
        ]);

        $this->assertFalse($state->isInstalled());

        $state->markInstalled();

        $this->assertTrue($state->isInstalled());

        $pending = (string) file_get_contents(
            $this->sandbox.'/pending.json',
        );

        $this->assertStringNotContainsString(
            'password',
            strtolower($pending),
        );
        $this->assertStringNotContainsString(
            'app_key',
            strtolower($pending),
        );
    }

    public function test_runtime_can_boot_fresh_installer_without_permanent_app_key_or_database_session(): void
    {
        config([
            'app.env' => 'production',
            'installer.force_uninstalled' => true,
        ]);

        file_put_contents(
            $this->sandbox.'/.env',
            "APP_KEY=\n",
        );

        config([
            'app.key' => null,
            'session.driver' => 'database',
            'cache.default' => 'database',
            'queue.default' => 'database',
        ]);

        app(InstallerRuntime::class)->bootstrap();

        $key = (string) config('app.key');

        $this->assertStringStartsWith('base64:', $key);
        $this->assertSame('file', config('session.driver'));
        $this->assertSame('file', config('cache.default'));
        $this->assertSame('sync', config('queue.default'));
        $this->assertFileExists(
            $this->sandbox.'/installer.key',
        );
    }
}
