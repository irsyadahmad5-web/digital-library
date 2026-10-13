<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicPresenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_heartbeat_counts_active_sessions_without_storing_ip(): void
    {
        $this->withSession(['presence_test' => true]);
        $this->postJson('/presence/heartbeat')
            ->assertOk()
            ->assertJsonPath('online', 1)
            ->assertJsonPath('window_seconds', 120)
            ->assertHeader('Cache-Control', 'no-store, private');

        // A second test request without a persistent cookie is a separate session.
        // Real browsers send their stable session cookie on every heartbeat.
        $this->assertDatabaseCount('public_presence', 1);
        $this->assertSame(['visitor_hash', 'last_seen_at'],
            array_keys((array) DB::table('public_presence')->first()));
    }

    public function test_inactive_sessions_are_excluded(): void
    {
        DB::table('public_presence')->insert([
            'visitor_hash' => str_repeat('a', 64),
            'last_seen_at' => now()->subMinutes(5),
        ]);

        $this->postJson('/presence/heartbeat')->assertJsonPath('online', 1);
    }
}
