<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Services\Auth\ActiveSessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

class ActiveSessionGuardTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database']);
    }

    public function test_has_active_session_is_false_with_no_session_rows(): void
    {
        $user = $this->psychometrician();

        $this->assertFalse((new ActiveSessionGuard)->hasActiveSession($user));
    }

    public function test_has_active_session_is_true_for_a_fresh_session_row(): void
    {
        $user = $this->psychometrician();
        $this->insertSessionRow($user->id, now()->timestamp);

        $this->assertTrue((new ActiveSessionGuard)->hasActiveSession($user));
    }

    public function test_has_active_session_is_false_for_a_row_past_the_session_lifetime(): void
    {
        $user = $this->psychometrician();
        $this->insertSessionRow($user->id, now()->subMinutes((int) config('session.lifetime') + 5)->timestamp);

        $this->assertFalse((new ActiveSessionGuard)->hasActiveSession($user));
    }

    public function test_is_oldest_active_session_is_true_when_it_is_the_only_one(): void
    {
        $user = $this->psychometrician();
        $sessionId = $this->insertSessionRow($user->id, now()->timestamp);

        $this->assertTrue((new ActiveSessionGuard)->isOldestActiveSession($user, $sessionId));
    }

    public function test_is_oldest_active_session_is_false_for_a_later_arriving_session(): void
    {
        $user = $this->psychometrician();
        $olderId = $this->insertSessionRow($user->id, now()->subMinute()->timestamp);
        $newerId = $this->insertSessionRow($user->id, now()->timestamp);

        $guard = new ActiveSessionGuard;

        $this->assertTrue($guard->isOldestActiveSession($user, $olderId));
        $this->assertFalse($guard->isOldestActiveSession($user, $newerId));
    }

    public function test_is_oldest_active_session_ignores_other_users_sessions(): void
    {
        $user = $this->psychometrician();
        $otherUser = $this->psychometrician();
        $this->insertSessionRow($otherUser->id, now()->timestamp);
        $sessionId = $this->insertSessionRow($user->id, now()->timestamp);

        $this->assertTrue((new ActiveSessionGuard)->isOldestActiveSession($user, $sessionId));
    }

    public function test_force_logout_removes_every_session_row_for_that_user_only(): void
    {
        $user = $this->psychometrician();
        $otherUser = $this->psychometrician();
        $this->insertSessionRow($user->id, now()->timestamp);
        $this->insertSessionRow($user->id, now()->timestamp);
        $otherSessionId = $this->insertSessionRow($otherUser->id, now()->timestamp);

        (new ActiveSessionGuard)->forceLogout($user);

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertTrue(DB::table('sessions')->where('id', $otherSessionId)->exists());
    }

    private function insertSessionRow(int $userId, int $lastActivity): string
    {
        $id = Str::random(40);

        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => base64_encode(serialize([])),
            'last_activity' => $lastActivity,
        ]);

        return $id;
    }
}
