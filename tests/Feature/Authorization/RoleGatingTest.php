<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

class RoleGatingTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    public function test_guidance_counselor_cannot_access_user_management(): void
    {
        $counselor = $this->guidanceCounselor();

        $this->actingAs($counselor)->get(route('users.index'))->assertForbidden();
    }

    public function test_guidance_counselor_cannot_access_settings(): void
    {
        $counselor = $this->guidanceCounselor();

        $this->actingAs($counselor)->get(route('settings.edit'))->assertForbidden();
    }

    public function test_psychometrician_cannot_access_flagged_cases(): void
    {
        $psychometrician = $this->psychometrician();

        $this->actingAs($psychometrician)->get(route('flagged-cases.index'))->assertForbidden();
    }

    public function test_an_inactive_users_login_is_silently_blocked(): void
    {
        $user = User::factory()->psychometrician()->create(['is_active' => false, 'password' => bcrypt('password')]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $user = $this->psychometrician();
        RateLimiter::clear(mb_strtolower($user->email).'|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $response = $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password']);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('seconds', session('errors')->first('email'));
    }

    public function test_a_user_cannot_deactivate_their_own_account(): void
    {
        $psychometrician = $this->psychometrician();

        $response = $this->actingAs($psychometrician)->patch(route('users.deactivate', $psychometrician));

        $response->assertSessionHasErrors('deactivate');
        $this->assertTrue($psychometrician->fresh()->is_active);
    }

    /**
     * The only way to reach this guard is if the acting user themselves is
     * not counted among the active total (an inactive psychometrician
     * account, e.g. one deactivated moments earlier by someone else) —
     * deactivating yourself while active is already blocked by the
     * self-deactivation check above. Uses actingAs() to simulate this
     * account being authenticated regardless of is_active, since the
     * safety net in UserPolicy::deactivate() must hold even if some future
     * caller (bulk action, API) reaches it by a path other than the login
     * form, which already excludes inactive users.
     */
    public function test_the_last_active_psychometrician_cannot_be_deactivated(): void
    {
        $onlyActivePsychometrician = $this->psychometrician();
        $inactiveActor = User::factory()->psychometrician()->create(['is_active' => false]);

        $response = $this->actingAs($inactiveActor)->patch(route('users.deactivate', $onlyActivePsychometrician));

        $response->assertSessionHasErrors('deactivate');
        $this->assertTrue($onlyActivePsychometrician->fresh()->is_active);
    }

    public function test_a_psychometrician_can_be_deactivated_when_another_remains_active(): void
    {
        $toDeactivate = $this->psychometrician();
        $this->psychometrician();
        $actingAdmin = $this->psychometrician();

        $response = $this->actingAs($actingAdmin)->patch(route('users.deactivate', $toDeactivate));

        $response->assertSessionDoesntHaveErrors('deactivate');
        $this->assertFalse($toDeactivate->fresh()->is_active);
    }
}
