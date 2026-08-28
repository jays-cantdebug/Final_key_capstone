<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\Auth\ActiveSessionGuard;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * Credentials are validated *without* logging in first (`Auth::validate()`,
     * not `Auth::attempt()`), so a correct-password-but-already-logged-in-
     * elsewhere attempt can be rejected before any session is ever
     * established for it — `Auth::attempt()` logs the user in as a side
     * effect of returning `true`, which would be too late to cleanly
     * undo. `is_active` stays folded into the same credentials array
     * Auth::validate() checks, so a deactivated account still fails at
     * this exact point with the exact same generic message as a wrong
     * password — that anti-enumeration property is unchanged.
     *
     * The single-active-session check only runs *after* credentials are
     * confirmed correct, so the distinct "already logged in elsewhere"
     * message is only ever shown to someone who has already proven they
     * know the account's password — it can't be used to probe whether an
     * email/password pair is valid.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            'email' => $this->string('email')->toString(),
            'password' => $this->string('password')->toString(),
            'is_active' => true,
        ];

        if (! Auth::validate($credentials)) {
            RateLimiter::hit($this->throttleKey());

            // Always attached to `password`, never `email` — whether the
            // email doesn't exist or the password is simply wrong, the
            // login page must show the exact same tooltip in the exact
            // same place, or an attacker could tell the two cases apart
            // and enumerate valid staff accounts.
            throw ValidationException::withMessages([
                'password' => trans('auth.failed'),
            ]);
        }

        /** @var User $user */
        $user = User::query()->where('email', $credentials['email'])->firstOrFail();

        if (app(ActiveSessionGuard::class)->hasActiveSession($user)) {
            // Not a credential-guessing attempt — the password was correct
            // — so this deliberately does not count against the rate
            // limiter the way a wrong password does.
            throw ValidationException::withMessages([
                'password' => 'This account is already logged in elsewhere. Please log out from the other device first.',
            ]);
        }

        Auth::login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
