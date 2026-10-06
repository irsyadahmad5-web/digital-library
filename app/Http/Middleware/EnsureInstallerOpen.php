<?php

namespace App\Http\Middleware;

use App\Modules\Installer\Application\InstallerState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstallerOpen
{
    public function __construct(
        private readonly InstallerState $state,
    ) {}

    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        if ($this->state->isInstalled()) {
            return redirect()->route('home');
        }

        $response = $next($request);

        $response->headers->set(
            'Cache-Control',
            'no-store, private',
        );
        $response->headers->set(
            'X-Robots-Tag',
            'noindex, nofollow, noarchive',
        );

        return $response;
    }
}
