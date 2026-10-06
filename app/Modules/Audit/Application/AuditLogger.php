<?php

namespace App\Modules\Audit\Application;

use App\Models\User;
use App\Modules\Audit\Domain\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * @var array<int, string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'authorization',
        'cookie',
        'secret',
        'app_key',
        'api_key',
        'access_token',
        'refresh_token',
    ];

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
        $userAgent = $request?->userAgent();

        return AuditLog::query()->create([
            'actor_id' => $actor?->getKey(),
            'event' => Str::limit($event, 120, ''),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId === null ? null : (string) $subjectId,
            'ip_address' => $request?->ip(),
            'user_agent' => is_string($userAgent)
                ? Str::limit($userAgent, 1000, '')
                : null,
            'metadata' => $metadata === []
                ? null
                : $this->sanitizeMetadata($metadata),
        ]);
    }

    /**
     * @param  array<mixed>  $metadata
     * @return array<mixed>
     */
    private function sanitizeMetadata(array $metadata, int $depth = 0): array
    {
        if ($depth >= 6) {
            return ['_truncated' => true];
        }

        $sanitized = [];

        foreach (array_slice($metadata, 0, 100, true) as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeMetadata(
                    $value,
                    $depth + 1,
                );

                continue;
            }

            if (is_string($value)) {
                $sanitized[$key] = Str::limit($value, 4000, '');

                continue;
            }

            if (
                is_null($value)
                || is_bool($value)
                || is_int($value)
                || is_float($value)
            ) {
                $sanitized[$key] = $value;

                continue;
            }

            $sanitized[$key] = '[UNSUPPORTED]';
        }

        return $sanitized;
    }
}
