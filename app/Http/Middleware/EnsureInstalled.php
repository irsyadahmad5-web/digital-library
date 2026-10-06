<?php

namespace App\Http\Middleware;

use App\Modules\Installer\Application\InstallerState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    public function __construct(
        private readonly InstallerState $state,
    ) {}

    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        if (
            $request->is('install')
            || $request->is('install/*')
            || $this->state->isInstalled()
        ) {
            return $next($request);
        }

        return redirect()->route('install.index');
    }
}
