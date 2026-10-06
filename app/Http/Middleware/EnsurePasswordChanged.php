<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * @var array<int, string>
     */
    private const ALLOWED_ROUTES = [
        'admin.profile.edit',
        'admin.password.update',
        'admin.logout',
    ];

    public function handle(
        Request $request,
        Closure $next,
    ): Response|RedirectResponse|JsonResponse {
        $user = $request->user();

        if (
            ! $user
            || ! $user->force_password_change
            || in_array($request->route()?->getName(), self::ALLOWED_ROUTES, true)
        ) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Password wajib diperbarui sebelum melanjutkan.',
            ], Response::HTTP_LOCKED);
        }

        return redirect()
            ->route('admin.profile.edit')
            ->with(
                'status',
                'Password wajib diperbarui sebelum mengakses fitur admin lainnya.',
            );
    }
}
