<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The password standard for every ACL account.
 *
 * A student picking a password on a phone, on a metered Nigerian connection, is
 * the person this rule is written for. The bar is deliberately the one every
 * major guidance body settles on: long enough to resist guessing, varied enough
 * that it is not a single dictionary word or a reused pattern, and not one of
 * the handful of passwords that top every breach corpus.
 *
 * Length does the heavy lifting. Complexity rules push people towards
 * "Password1!", which is worse than a long passphrase, so the character-class
 * requirements are modest and the minimum length is the real control.
 */
class StrongPassword implements ValidationRule
{
    /** Refused outright, whatever else the password satisfies. */
    private const COMMON = [
        'password', 'password1', 'password123', '12345678', '123456789',
        'qwerty123', 'qwertyuiop', 'abc12345', 'iloveyou', 'admin123',
        'welcome1', 'letmein1', 'monkey12', 'football', 'baseball',
        'trustno1', 'dragon12', 'passw0rd', 'p@ssw0rd', 'p@ssword1',
        'jamb1234', 'student123', 'matriculation',
    ];

    public function __construct(
        private readonly int $min = 8,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be text.');

            return;
        }

        if (mb_strlen($value) < $this->min) {
            $fail("The :attribute must be at least {$this->min} characters long.");

            return;
        }

        // Length is the main control, so the ceiling is generous enough to allow
        // a passphrase but bounded so nothing unbounded is stored or hashed.
        if (mb_strlen($value) > 128) {
            $fail('The :attribute must be 128 characters or fewer.');

            return;
        }

        if (in_array(mb_strtolower($value), self::COMMON, true)) {
            $fail('That :attribute is one of the most commonly guessed passwords. Please choose something harder to guess.');

            return;
        }

        if (! preg_match('/[A-Za-z]/', $value) || ! preg_match('/\d/', $value)) {
            // Length is the real control, so a passphrase is not made to carry a
            // digit it does not need. The character-class requirement only
            // applies to shorter passwords, where it does the work instead.
            if (mb_strlen($value) < 12) {
                $fail('The :attribute must contain at least one letter and one number, or be at least 12 characters long.');

                return;
            }
        }

        // A long run of one repeated character, or a straight keyboard run, is
        // not meaningfully stronger than the word it stands in for.
        if (preg_match('/(.)\1{4,}/u', $value)) {
            $fail('The :attribute cannot repeat the same character five or more times in a row.');

            return;
        }

        if (preg_match('/(?:12345|qwert|asdfg|zxcvb|abcd)/i', $value) && mb_strlen($value) < 12) {
            $fail('The :attribute cannot start with a keyboard or number sequence.');
        }
    }
}
