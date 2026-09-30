<?php

namespace Tests\Feature;

use App\Rules\StrongPassword;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * The password standard.
 *
 * These pin the behaviour a student actually experiences: a memorable passphrase
 * is accepted, and the obvious weak choices are refused with a message that says
 * why, rather than a bare "validation failed".
 */
class StrongPasswordTest extends TestCase
{
    private function fails(string $password): ?string
    {
        $validator = Validator::make(
            ['password' => $password],
            ['password' => [new StrongPassword]],
        );

        return $validator->fails() ? $validator->errors()->first('password') : null;
    }

    private function passes(string $password): bool
    {
        return $this->fails($password) === null;
    }

    public function test_a_reasonable_password_is_accepted(): void
    {
        // Length is the control, so a readable passphrase must pass. Rejecting
        // these would push people towards P@ssw0rd1.
        $this->assertTrue($this->passes('correct horse battery'));
        $this->assertTrue($this->passes('naija4study'));
        $this->assertTrue($this->passes('Akwa1bena2026'));
    }

    public function test_a_short_password_is_refused_with_a_length_message(): void
    {
        $this->assertStringContainsString('at least 8', (string) $this->fails('Ab1cdef'));
    }

    public function test_a_password_without_a_number_is_refused(): void
    {
        $this->assertStringContainsString('one letter and one number', (string) $this->fails('abcdefghij'));
    }

    public function test_a_password_without_a_letter_is_refused(): void
    {
        $this->assertStringContainsString('one letter and one number', (string) $this->fails('1234567890'));
    }

    public function test_the_most_guessed_passwords_are_refused_by_name(): void
    {
        foreach (['password1', 'Password123', 'qwerty123', '12345678', 'iloveyou', 'student123'] as $weak) {
            $this->assertNotNull($this->fails($weak), "[{$weak}] should have been refused");
        }
    }

    public function test_a_common_password_is_refused_case_insensitively(): void
    {
        $this->assertStringContainsString('commonly guessed', (string) $this->fails('PassWord1'));
    }

    public function test_a_single_repeated_character_is_refused(): void
    {
        $this->assertStringContainsString('repeat the same character', (string) $this->fails('aaaaaaaaa1'));
    }

    public function test_a_keyboard_sequence_is_refused(): void
    {
        $this->assertStringContainsString('keyboard', (string) $this->fails('qwerty12345'));
    }

    public function test_an_overlong_password_is_refused(): void
    {
        $this->assertStringContainsString('128 characters or fewer', (string) $this->fails(str_repeat('ab1', 60)));
    }
}
