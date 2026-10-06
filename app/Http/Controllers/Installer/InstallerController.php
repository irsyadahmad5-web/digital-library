<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Installer\InstallerAdminRequest;
use App\Http\Requests\Installer\InstallerDatabaseRequest;
use App\Modules\Installer\Application\InstallerDatabase;
use App\Modules\Installer\Application\InstallerManager;
use App\Modules\Installer\Application\InstallerRequirementChecker;
use App\Modules\Installer\Application\InstallerState;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class InstallerController extends Controller
{
    public function __construct(
        private readonly InstallerState $state,
        private readonly InstallerRequirementChecker $requirements,
        private readonly InstallerDatabase $database,
        private readonly InstallerManager $manager,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $pending = $this->state->pending();

        if (($pending['environment_written'] ?? false) === true) {
            return redirect()->route('install.admin');
        }

        return view('install.wizard', [
            'step' => 'requirements',
            'requirements' => $this->requirements->check(),
            'defaults' => [
                'app_name' => $pending['app_name'] ?? 'Digital Library',
                'app_url' => $pending['app_url'] ?? rtrim($request->root(), '/'),
                'timezone' => $pending['timezone'] ?? 'Asia/Jakarta',
                'db_connection' => $pending['db_connection'] ?? 'mysql',
                'db_host' => $pending['db_host'] ?? '127.0.0.1',
                'db_port' => $pending['db_port'] ?? 3306,
                'db_database' => $pending['db_database'] ?? 'digital_library',
                'db_username' => $pending['db_username'] ?? '',
                'trusted_proxies' => $pending['trusted_proxies'] ?? '',
            ],
        ]);
    }

    public function database(
        InstallerDatabaseRequest $request,
    ): RedirectResponse {
        try {
            $result = $this->manager->prepareDatabase(
                $request->validated(),
            );
        } catch (DomainException|RuntimeException $exception) {
            return back()
                ->withInput($request->except('db_password'))
                ->withErrors([
                    'database' => $exception->getMessage(),
                ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->except('db_password'))
                ->withErrors([
                    'database' => 'Konfigurasi installer gagal diproses. '
                        .'Periksa log server untuk detail teknis.',
                ]);
        }

        return redirect()
            ->route('install.admin')
            ->with(
                'status',
                'Koneksi database berhasil: '
                    .$result['version']
                    .'. Konfigurasi .env sudah disimpan.',
            );
    }

    public function admin(): View|RedirectResponse
    {
        $pending = $this->state->pending();

        if (
            ! $this->state->isPending()
            || ($pending['environment_written'] ?? false) !== true
        ) {
            return redirect()->route('install.index');
        }

        return view('install.wizard', [
            'step' => 'admin',
            'pending' => $pending,
            'databaseReady' => $this->database
                ->currentConnectionReady(),
        ]);
    }

    public function reconfigure(): RedirectResponse
    {
        $pending = $this->state->pending();

        if ($pending !== []) {
            $this->state->markPending([
                ...$pending,
                'environment_written' => false,
            ]);
        }

        return redirect()->route('install.index');
    }

    public function complete(
        InstallerAdminRequest $request,
    ): View|RedirectResponse {
        try {
            $result = $this->manager->complete(
                $request->validated(),
            );
        } catch (DomainException|RuntimeException $exception) {
            return back()
                ->withInput($request->except([
                    'password',
                    'password_confirmation',
                ]))
                ->withErrors([
                    'installation' => $exception->getMessage(),
                ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->except([
                    'password',
                    'password_confirmation',
                ]))
                ->withErrors([
                    'installation' => 'Instalasi belum dapat diselesaikan. '
                        .'Tidak ada credential yang ditampilkan; '
                        .'periksa log server untuk detail teknis.',
                ]);
        }

        return view('install.wizard', [
            'step' => 'complete',
            'adminEmail' => $result['user']->email,
            'appUrl' => (string) config('app.url'),
        ]);
    }
}
