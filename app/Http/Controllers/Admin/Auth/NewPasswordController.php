<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Application\AuditLogger;
use App\Modules\Identity\Application\SessionRevoker;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function store(
        Request $request,
        AuditLogger $audit,
        SessionRevoker $sessions,
    ): RedirectResponse {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => [
                'required',
                'string',
                'max:255',
                'confirmed',
                PasswordRule::min(10)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $status = Password::reset(
            [
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $validated['token'],
            ],
            function (User $user, string $password) use (
                $request,
                $audit,
                $sessions,
            ): void {
                $user->forceFill([
                    'password' => $password,
                    'password_changed_at' => now(),
                    'force_password_change' => false,
                    'remember_token' => Str::random(60),
                ])->save();

                $sessions->revokeAll($user);

                event(new PasswordReset($user));

                $audit->log(
                    'auth.password_reset.completed',
                    actor: $user,
                    metadata: ['sessions_revoked' => true],
                    request: $request,
                );
            },
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Password berhasil diperbarui. Silakan login.');
    }
}
