<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Refuses passwords built on a well-known word, checked locally (no
 * network, unlike Password::uncompromised()). Two forms are checked: the
 * letters alone, and the word part (leading/trailing digits and symbols
 * removed) with look-alikes undone (0→o, 1→l, 3→e, 4/@→a, 5/$→s, 7→t), so
 * "Password2026!" and "P@ssw0rd-1234" are both caught as "password". Part of
 * Password::defaults() (AppServiceProvider); docs/BUG_LOG.md L1.
 */
class NotCommonPassword implements ValidationRule
{
    public const MESSAGE = 'This password is too common or too easy to guess. Please choose a different one.';

    /** Lowercase letters only (after the normalization above). */
    private const COMMON = [
        'password', 'passwd', 'passcode', 'qwerty', 'qwertyuiop', 'asdfgh', 'asdfghjkl', 'zxcvbnm', 'abc', 'abcdef',
        'abcdefgh', 'letmein', 'welcome', 'admin', 'administrator', 'superadmin', 'root', 'user', 'guest', 'login',
        'iloveyou', 'loveyou', 'monkey', 'dragon', 'sunshine', 'princess', 'football', 'baseball', 'basketball', 'master',
        'shadow', 'secret', 'changeme', 'default', 'trustno', 'starwars', 'whatever', 'freedom', 'hello', 'helloworld',
        'computer', 'internet', 'samsung', 'iphone', 'google', 'facebook', 'pokemon', 'naruto', 'batman', 'superman',
        'normi', 'normiadmin', 'northern', 'northernmindanao', 'mindanao', 'northernmindanaocolleges', 'colleges', 'college', 'school', 'student',
        'teacher', 'guidance', 'counselor', 'counsellor', 'psychometrician', 'psychology', 'assessment', 'philippines', 'pilipinas', 'manila',
        'cagayan', 'butuan', 'mabuhay', 'mahalkita', 'iloveu', 'jesus', 'jesuschrist', 'blessed', 'godisgood', 'family',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $lower = mb_strtolower($value);
        // The word part, without leading/trailing digits and symbols ("-2026!"),
        // so look-alikes are undone only inside it ("p@ssw0rd").
        $core = preg_replace('/^[^a-z]+|[^a-z]+$/', '', $lower) ?? '';
        $candidates = [
            preg_replace('/[^a-z]/', '', $lower) ?? '',
            preg_replace('/[^a-z]/', '', strtr($core, ['0' => 'o', '1' => 'l', '3' => 'e', '4' => 'a', '@' => 'a', '5' => 's', '$' => 's', '7' => 't'])) ?? '',
        ];

        foreach ($candidates as $letters) {
            if ($letters === '' || in_array($letters, self::COMMON, true)) {
                $fail(self::MESSAGE);

                return;
            }
        }
    }
}
