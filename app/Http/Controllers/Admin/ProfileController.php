<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProfileUpdateRequest;
use App\Modules\Audit\Application\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $sessions = collect();

        if (config('session.driver') === 'database') {
            $sessions = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->orderByDesc('last_activity')
                ->get()
                ->map(fn ($session) => [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    'last_activity' => $session->last_activity,
                    'is_current' => hash_equals($request->session()->getId(), $session->id),
                ]);
        }

        return Inertia::render('Admin/Profile', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles()->pluck('name')->all(),
                'permissions' => $user->permissions(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'last_login_ip' => $user->last_login_ip,
            ],
            'sessions' => $sessions,
        ]);
    }

    public function update(
        ProfileUpdateRequest $request,
        AuditLogger $audit,
    ): RedirectResponse {
        $user = $request->user();
        $validated = $request->safe()->only(['name', 'email']);

        $emailChanged = $user->email !== $validated['email'];

        $user->fill($validated);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        $audit->log('admin.profile.updated', actor: $user, subjectType: 'user', subjectId: $user->getKey(), request: $request);

        return back()->with('status', 'Profil berhasil diperbarui.');
    }
}
