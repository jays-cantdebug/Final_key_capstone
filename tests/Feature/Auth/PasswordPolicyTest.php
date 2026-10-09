<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Rules\NotCommonPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

/**
 * Every new or changed password (user create, admin reset, profile) must
 * be at least 12 characters with upper- and lowercase letters and a number,
 * and not built on a common word (checked locally). Existing passwords keep
 * working; the forms show the rule.
 */
class PasswordPolicyTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    private const STRONG = 'Calm-River-Stone-27';

    /** @return array<string, array{string, string}> weak password => expected message fragment */
    public static function weakPasswords(): array
    {
        return [
            'too short' => ['Short-Pw-7', 'at least 12 characters'],
            'no uppercase' => ['calm-river-stone-27', 'one uppercase and one lowercase letter'],
            'no number' => ['Calm-River-Stone', 'at least one number'],
            'common word + year' => ['Password2026!', NotCommonPassword::MESSAGE],
            'common word with look-alikes' => ['P@ssw0rd-12345', NotCommonPassword::MESSAGE],
            'the school name' => ['Normi-Admin-2026', NotCommonPassword::MESSAGE],
        ];
    }

    /**
     * @dataProvider weakPasswords
     */
    public function test_a_weak_password_is_refused_when_creating_a_user(string $password, string $message): void
    {
        $this->actingAs($this->psychometrician())->post(route('users.store'), [
            'name' => 'New Counselor',
            'email' => 'new.counselor@example.test',
            'role_id' => $this->counselorRoleId(),
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertSessionHasErrors('password');

        $this->assertStringContainsString($message, implode(' ', session('errors')->get('password')));
        $this->assertDatabaseMissing('users', ['email' => 'new.counselor@example.test']);
    }

    public function test_a_strong_password_is_accepted_everywhere_it_can_be_set(): void
    {
        $admin = $this->psychometrician();

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'New Counselor',
            'email' => 'new.counselor@example.test',
            'role_id' => $this->counselorRoleId(),
            'password' => self::STRONG,
            'password_confirmation' => self::STRONG,
        ])->assertSessionHasNoErrors();
        $created = User::query()->where('email', 'new.counselor@example.test')->sole();
        $this->assertTrue(Hash::check(self::STRONG, $created->password));

        $this->actingAs($admin)->patch(route('users.reset-password', $created), [
            'password' => 'Quiet-Harbor-Lamp-58',
            'password_confirmation' => 'Quiet-Harbor-Lamp-58',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Quiet-Harbor-Lamp-58', $created->fresh()->password));

        $this->actingAs($admin)->from('/profile')->put('/password', [
            'current_password' => 'password',
            'password' => 'Bright-Maple-Door-91',
            'password_confirmation' => 'Bright-Maple-Door-91',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Bright-Maple-Door-91', $admin->fresh()->password));
    }

    public function test_the_admin_reset_and_the_profile_refuse_weak_passwords_too(): void
    {
        $admin = $this->psychometrician();
        $counselor = $this->guidanceCounselor();

        $this->actingAs($admin)->patch(route('users.reset-password', $counselor), [
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');

        $this->actingAs($admin)->from('/profile')->put('/password', [
            'current_password' => 'password',
            'password' => 'Password2026!',
            'password_confirmation' => 'Password2026!',
        ])->assertSessionHasErrorsIn('updatePassword', 'password');
    }

    public function test_an_existing_weak_password_still_logs_in(): void
    {
        $user = User::factory()->psychometrician()->create(['password' => Hash::make('password')]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    private function counselorRoleId(): int
    {
        return $this->guidanceCounselor()->role_id;
    }

    public function test_every_new_password_form_states_the_rule(): void
    {
        $hint = 'At least 12 characters, with upper- and lowercase letters and a number.';
        $admin = $this->psychometrician();

        $this->actingAs($admin)->get(route('users.create'))->assertOk()->assertSee($hint);
        $this->actingAs($admin)->get(route('users.show', $this->guidanceCounselor()))->assertOk()->assertSee($hint);
        $this->actingAs($admin)->get('/profile')->assertOk()->assertSee($hint);
        auth()->logout();
        $this->get('/reset-password/some-token?email=someone@example.test')->assertOk()->assertSee($hint);
    }
}
