<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Domain\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = AuditLog::query()
            ->with('actor:id,name,email')
            ->latest('id')
            ->paginate(50)
            ->through(fn (AuditLog $log) => [
                'id' => $log->getKey(),
                'event' => $log->event,
                'actor' => $log->actor ? [
                    'name' => $log->actor->name,
                    'email' => $log->actor->email,
                ] : null,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'metadata' => $log->metadata,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/AuditLog', [
            'logs' => $logs,
        ]);
    }
}
