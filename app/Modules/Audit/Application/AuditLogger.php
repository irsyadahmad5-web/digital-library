<?php

namespace App\Modules\Audit\Application;

use App\Models\User;
use App\Modules\Audit\Domain\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $event,
        ?User $actor = null,
        ?string $subjectType = null,
        int|string|null $subjectId = null,
        array $metadata = [],
        ?Request $request = null,
    ): AuditLog {
        $request ??= request();

        return AuditLog::query()->create([
            'actor_id' => $actor?->getKey(),
            'event' => $event,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId === null ? null : (string) $subjectId,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata ?: null,
        ]);
    }
}
