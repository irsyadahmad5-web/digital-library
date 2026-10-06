<?php

namespace App\Modules\Identity\Application;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class SessionRevoker
{
    public function revokeOther(User $user, ?string $exceptSessionId): int
    {
        if (config('session.driver') !== 'database') {
            return 0;
        }

        $query = DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->getKey());

        if (is_string($exceptSessionId) && $exceptSessionId !== '') {
            $query->where('id', '!=', $exceptSessionId);
        }

        return $query->delete();
    }

    public function revokeAll(User $user): int
    {
        return $this->revokeOther($user, null);
    }
}
