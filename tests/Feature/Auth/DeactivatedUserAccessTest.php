<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

/**
 * Deactivating a user ends their access at once: their open session, a
 * remember-me cookie, and (via EnsureUserIsActive) any session that
 * survives. Uses the database session driver, as in production, and replays
 * the cookies the login response actually set, the way a browser would.
 */
class DeactivatedUserAccessTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    private const PASSWORD = 'Secret-Pass-123';

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
    }

    public function test_deactivating_a_logged_in_counselor_sends_their_next_request_to_login(): void
    {
        $admin = $this->psychometrician();
        $counselor = $this->guidanceCounselor(['password' => bcrypt(self::PASSWORD)]);
        $cookies = $this->logIn($counselor);

        $this->asBrowser($cookies, [config('session.cookie')])->get('/flagged-cases')->assertOk();

        $this->deactivate($admin, $counselor);

        $this->assertSame(0, DB::table('sessions')->where('user_id', $counselor->id)->count());
        $this->asBrowser($cookies, [config('session.cookie')])->get('/flagged-cases')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_deactivated_users_remember_me_cookie_no_longer_logs_them_in(): void
    {
        $admin = $this->psychometrician();
        $counselor = $this->guidanceCounselor(['password' => bcrypt(self::PASSWORD)]);
        $cookies = $this->logIn($counselor, remember: true);
        $recaller = $this->recallerName($cookies);
        $tokenBefore = $counselor->fresh()->remember_token;

        // Control: once the session has expired, the remember cookie alone
        // logs them back in while the account is active.
        $this->expireSessions($counselor);
        $this->asBrowser($cookies, [$recaller])->get('/flagged-cases')->assertOk();
        $this->expireSessions($counselor);

        $this->deactivate($admin, $counselor);

        $this->assertNotSame($tokenBefore, $counselor->fresh()->remember_token);
        $this->asBrowser($cookies, [$recaller])->get('/flagged-cases')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_session_that_survives_deactivation_is_logged_out_on_its_next_request(): void
    {
        // E.g. a session driver whose sessions can't be listed: only the
        // middleware stands between the user and the app.
        config(['session.driver' => 'array']);
        $counselor = $this->guidanceCounselor();
        $this->actingAs($counselor)->get('/flagged-cases')->assertOk();

        $counselor->forceFill(['is_active' => false])->save();

        $this->get('/flagged-cases')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => EnsureUserIsActive::MESSAGE]);
        $this->assertGuest();
    }

    public function test_the_logout_message_does_not_mention_deactivation(): void
    {
        $this->assertStringNotContainsStringIgnoringCase('deactivat', EnsureUserIsActive::MESSAGE);
        $this->assertStringNotContainsStringIgnoringCase('inactive', EnsureUserIsActive::MESSAGE);
    }

    public function test_a_reactivated_user_can_log_in_again_normally(): void
    {
        $admin = $this->psychometrician();
        $counselor = $this->guidanceCounselor(['password' => bcrypt(self::PASSWORD)]);
        $this->logIn($counselor, remember: true);
        $this->deactivate($admin, $counselor);

        // While deactivated, the login form refuses them as before.
        $this->forgetBrowser();
        $this->post('/login', ['email' => $counselor->email, 'password' => self::PASSWORD])->assertSessionHasErrors();
        $this->assertGuest();

        $this->forgetBrowser();
        $this->expireSessions($admin);
        $this->actingAs($admin)->patch(route('users.activate', $counselor))->assertRedirect();
        $this->assertTrue($counselor->fresh()->is_active);

        $cookies = $this->logIn($counselor, remember: true);
        $this->asBrowser($cookies, [config('session.cookie')])->get('/flagged-cases')->assertOk();
        $this->expireSessions($counselor);
        $this->asBrowser($cookies, [$this->recallerName($cookies)])->get('/flagged-cases')->assertOk();
    }

    public function test_deactivating_someone_else_leaves_an_active_users_session_and_token_alone(): void
    {
        $admin = $this->psychometrician();
        $active = $this->guidanceCounselor(['password' => bcrypt(self::PASSWORD)]);
        $other = $this->guidanceCounselor();
        $cookies = $this->logIn($active, remember: true);
        $token = $active->fresh()->remember_token;
        $sessions = DB::table('sessions')->where('user_id', $active->id)->count();

        $this->deactivate($admin, $other);

        $this->assertSame($token, $active->fresh()->remember_token);
        $this->assertSame($sessions, DB::table('sessions')->where('user_id', $active->id)->count());
        $this->asBrowser($cookies, [config('session.cookie')])->get('/flagged-cases')->assertOk();
        $this->expireSessions($active);
        $this->asBrowser($cookies, [$this->recallerName($cookies)])->get('/flagged-cases')->assertOk();
    }

    public function test_guests_the_login_page_and_the_student_device_routes_are_unaffected(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/flagged-cases')->assertRedirect(route('login'));
        $this->get('/s')->assertOk();

        foreach (Route::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();
            if ($route->uri() === 's' || str_starts_with($route->uri(), 's/')) {
                $this->assertNotContains('active', $middleware, "/{$route->uri()} must not need a login.");
            } elseif (in_array('auth', $middleware, true)) {
                $this->assertContains('active', $middleware, "/{$route->uri()} is authenticated but not checked for deactivation.");
                $this->assertLessThan(
                    array_search('single-session', $middleware, true),
                    array_search('active', $middleware, true),
                    "/{$route->uri()}: deactivation is checked before the single-session rule.",
                );
            }
        }
    }

    public function test_deactivation_still_writes_one_update_audit_entry_without_the_token(): void
    {
        $admin = $this->psychometrician();
        $counselor = $this->guidanceCounselor();

        $this->deactivate($admin, $counselor);

        $entries = DB::table('audit_logs')->where('module', 'User Management')->where('record_id', $counselor->id)->where('action', 'Update')->get();
        $this->assertCount(1, $entries);
        $this->assertStringNotContainsString('remember_token', (string) $entries->first()->new_values);
        $this->assertStringNotContainsString('remember_token', (string) $entries->first()->old_values);
    }

    /**
     * Log in through the login form and return the cookies it set, as the
     * browser would store them (already encrypted).
     *
     * @return array<string, string>
     */
    private function logIn(User $user, bool $remember = false): array
    {
        $this->forgetBrowser();
        $response = $this->post('/login', array_filter([
            'email' => $user->email,
            'password' => self::PASSWORD,
            'remember' => $remember ? '1' : null,
        ]));
        $response->assertRedirect();

        return collect($response->headers->getCookies())->mapWithKeys(fn ($cookie) => [$cookie->getName() => $cookie->getValue()])->all();
    }

    private function deactivate(User $admin, User $user): void
    {
        $this->forgetBrowser();
        $this->expireSessions($admin);
        $this->actingAs($admin)->patch(route('users.deactivate', $user))->assertRedirect();
        $this->assertFalse($user->fresh()->is_active);
    }

    /**
     * End a user's sessions the way the 120-minute lifetime would. A
     * remember-me cookie only takes over once the session is gone, and the
     * single-session rule refuses a second session while one is live (each
     * test request as the admin otherwise looks like a second browser).
     */
    private function expireSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    /**
     * A fresh "browser" carrying only the named cookies, exactly as set.
     *
     * @param  array<string, string>  $cookies
     * @param  list<string>  $names
     */
    private function asBrowser(array $cookies, array $names): static
    {
        $this->forgetBrowser();
        $this->withUnencryptedCookies([]);
        foreach ($names as $name) {
            $this->withUnencryptedCookie($name, $cookies[$name]);
        }

        return $this;
    }

    /**
     * @param  array<string, string>  $cookies
     */
    private function recallerName(array $cookies): string
    {
        $name = collect(array_keys($cookies))->first(fn (string $name): bool => str_starts_with($name, 'remember_web_'));
        $this->assertNotNull($name, 'Login with "remember" set no remember-me cookie.');

        return $name;
    }

    private function forgetBrowser(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
    }
}
