<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PasswordUpdateRequest;
use App\Modules\Audit\Application\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PasswordController extends Controller
{
    public function update(
        PasswordUpdateRequest $request,
        AuditLogger $audit,
    ): RedirectResponse {
        $user = $request->user();

        $user->forceFill([
            'password' => $request->string('password')->toString(),
            'password_changed_at' => now(),
            'force_password_change' => false,
            'remember_token' => Str::random(60),
        ])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        $audit->log('admin.password.updated', actor: $user, subjectType: 'user', subjectId: $user->getKey(), request: $request);

        return back()->with('status', 'Password berhasil diperbarui dan sesi lain telah dikeluarkan.');
    }
}
