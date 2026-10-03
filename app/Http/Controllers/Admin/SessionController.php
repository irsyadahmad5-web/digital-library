<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OtherSessionsRequest;
use App\Modules\Audit\Application\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SessionController extends Controller
{
    public function destroyOthers(
        OtherSessionsRequest $request,
        AuditLogger $audit,
    ): RedirectResponse {
        $user = $request->user();
        $deleted = 0;

        if (config('session.driver') === 'database') {
            $deleted = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        $user->forceFill([
            'remember_token' => Str::random(60),
        ])->save();

        $audit->log('admin.sessions.revoked', actor: $user, metadata: [
            'revoked_count' => $deleted,
        ], request: $request);

        return back()->with('status', 'Sesi lain berhasil dikeluarkan.');
    }
}
