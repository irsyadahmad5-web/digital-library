<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Application\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ]);

        Password::sendResetLink(['email' => $validated['email']]);

        $audit->log('auth.password_reset.requested', metadata: [
            'email' => Str::lower($validated['email']),
        ], request: $request);

        return back()->with(
            'status',
            'Jika email terdaftar, tautan reset password telah dikirim.',
        );
    }
}
