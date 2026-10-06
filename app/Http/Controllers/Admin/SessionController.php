<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OtherSessionsRequest;
use App\Modules\Audit\Application\AuditLogger;
use App\Modules\Identity\Application\SessionRevoker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class SessionController extends Controller
{
    public function destroyOthers(
        OtherSessionsRequest $request,
        AuditLogger $audit,
        SessionRevoker $sessions,
    ): RedirectResponse {
        $user = $request->user();
        $deleted = $sessions->revokeOther(
            $user,
            $request->session()->getId(),
        );

        $user->forceFill([
            'remember_token' => Str::random(60),
        ])->save();

        $audit->log('admin.sessions.revoked', actor: $user, metadata: [
            'revoked_count' => $deleted,
        ], request: $request);

        return back()->with('status', 'Sesi lain berhasil dikeluarkan.');
    }
}
