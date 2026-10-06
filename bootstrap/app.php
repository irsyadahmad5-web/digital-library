<?php

use App\Http\Middleware\AdminSecurityHeaders;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\EnsureInstallerOpen;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PublicMaintenanceMode;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ValidateHost;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PREFIX,
        );

        $middleware->web(
            prepend: [
                EnsureInstalled::class,
                ValidateHost::class,
            ],
            append: [
                SecurityHeaders::class,
                HandleInertiaRequests::class,
            ],
        );

        $middleware->alias([
            'active' => EnsureActiveUser::class,
            'permission' => EnsurePermission::class,
            'password.changed' => EnsurePasswordChanged::class,
            'install.open' => EnsureInstallerOpen::class,
            'admin.headers' => AdminSecurityHeaders::class,
            'site.maintenance' => PublicMaintenanceMode::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
