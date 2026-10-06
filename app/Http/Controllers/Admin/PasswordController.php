<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PasswordUpdateRequest;
use App\Modules\Audit\Application\AuditLogger;
use App\Modules\Identity\Application\SessionRevoker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class PasswordController extends Controller
{
    public function update(
        PasswordUpdateRequest $request,
        AuditLogger $audit,
        SessionRevoker $sessions,
    ): RedirectResponse {
        $user = $request->user();

        $user->forceFill([
            'password' => $request->string('password')->toString(),
            'password_changed_at' => now(),
            'force_password_change' => false,
            'remember_token' => Str::random(60),
        ])->save();

        $sessions->revokeOther(
            $user,
            $request->session()->getId(),
        );
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        $audit->log('admin.password.updated', actor: $user, subjectType: 'user', subjectId: $user->getKey(), request: $request);

        return back()->with('status', 'Password berhasil diperbarui dan sesi lain telah dikeluarkan.');
    }
}
