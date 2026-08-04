<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

/**
 * Single-session-per-account enforcement. A second login attempt is
 * blocked (not the older session kicked) while an account already has a
 * non-expired session — see LoginRequest::authenticate() and
 * ActiveSessionGuard. This requires the real `database` session driver
 * (phpunit.xml pins `array` for the rest of the suite, under which this
 * feature has nothing to check against), so each test switches it
 * explicitly; the `sessions` table is already part of the base users
 * migration and migrates fine into the sqlite in-memory test database.
 */
class SingleSessionTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database']);
    }

    public function test_a_second_login_is_blocked_while_the_first_session_is_active(): void
    {
        $user = User::factory()->psychometrician()->create();
        $this->insertSessionRow($user->id, now()->timestamp);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_login_succeeds_once_the_prior_session_has_expired(): void
    {
        $user = User::factory()->psychometrician()->create();
        $this->insertSessionRow($user->id, now()->subMinutes((int) config('session.lifetime') + 5)->timestamp);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_active_remembered_session_blocks_a_new_login_the_same_as_any_other(): void
    {
        $user = User::factory()->psychometrician()->create();
        $this->insertSessionRow($user->id, now()->timestamp);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => 'on',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_a_wrong_password_is_not_confused_with_an_already_logged_in_block(): void
    {
        $user = User::factory()->psychometrician()->create();
        $this->insertSessionRow($user->id, now()->timestamp);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertStringContainsString(
            'credentials do not match',
            session('errors')->first('password')
        );
        $this->assertGuest();
    }

    public function test_force_logout_immediately_unblocks_a_new_login(): void
    {
        $user = User::factory()->psychometrician()->create();
        $this->insertSessionRow($user->id, now()->timestamp);

        DB::table('sessions')->where('user_id', $user->id)->delete();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_force_logout_action_ends_the_users_sessions(): void
    {
        $admin = $this->psychometrician();
        $target = $this->psychometrician();
        $this->insertSessionRow($target->id, now()->timestamp);
        $this->insertSessionRow($target->id, now()->timestamp);

        $response = $this->actingAs($admin)->patch(route('users.force-logout', $target));

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
    }

    /**
     * The scenario the login-time check alone can't catch: a "Remember Me"
     * cookie re-authenticates silently on whatever route is visited next,
     * never touching LoginRequest at all. Simulates that second device by
     * inserting a later-arriving session row directly (mirroring what the
     * remember-me auto-login would produce) and driving a real
     * authenticated request under that exact session id — the
     * `single-session` middleware must end it, since an older row for the
     * same user already exists.
     */
    public function test_the_backstop_middleware_ends_a_later_arriving_session_for_the_same_user(): void
    {
        $user = User::factory()->psychometrician()->create();
        $this->insertSessionRow($user->id, now()->subMinute()->timestamp);
        $newerSessionId = $this->insertSessionRow($user->id, now()->timestamp);

        $response = $this
            ->withUnencryptedCookie(config('session.cookie'), $newerSessionId)
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
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
