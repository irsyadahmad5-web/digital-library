<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicPresenceController extends Controller
{
    public function heartbeat(Request $request): JsonResponse
    {
        // No IP address, user agent or identifying visitor information is stored.
        // Browser tabs sharing a session are counted as one active visitor.
        $visitorHash = hash_hmac('sha256', $request->session()->getId(), (string) config('app.key'));
        $now = now();

        DB::table('public_presence')->upsert(
            [['visitor_hash' => $visitorHash, 'last_seen_at' => $now]],
            ['visitor_hash'],
            ['last_seen_at'],
        );

        // Opportunistic cleanup; no persistent job or hot polling required.
        if (random_int(1, 40) === 1) {
            DB::table('public_presence')
                ->where('last_seen_at', '<', $now->copy()->subMinutes(15))
                ->delete();
        }

        $online = DB::table('public_presence')
            ->where('last_seen_at', '>=', $now->copy()->subSeconds(120))
            ->count();

        return response()->json(['online' => $online, 'window_seconds' => 120])
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
