<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Auth\PasswordChecks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;
use Tests\TestCase;

/** The validation rule form of PasswordChecks, for a current-password field. */
class PasswordChecksRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_wrong_password_fails_and_counts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (range(1, 5) as $attempt) {
            $this->assertTrue($this->validate('wrong-'.$attempt)->fails());
        }

        $validator = $this->validate('password');
        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('Too many wrong passwords.', $validator->errors()->first('password'));
    }

    public function test_the_right_password_passes_and_clears(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        PasswordChecks::failed($user);

        $this->assertTrue($this->validate('password')->passes());
        $this->assertFalse(PasswordChecks::tooMany($user));
    }

    public function test_an_empty_password_fails_without_counting(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (range(1, 5) as $attempt) {
            $this->assertTrue($this->validate('')->fails());
        }

        $this->assertFalse(PasswordChecks::tooMany($user));
    }

    private function validate(string $password): ValidatorInstance
    {
        return Validator::make(['password' => $password], ['password' => ['required', PasswordChecks::rule()]]);
    }
}
