<?php

namespace Tests\Feature\Components;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A field's error is tied to the field (aria-invalid, aria-describedby), not
 * only coloured, on the form components and the hand-rolled auth fields.
 */
class FormFieldErrorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_form_components_tie_an_error_to_their_field(): void
    {
        foreach (['<x-form-input name="email" label="Email" />', '<x-form-select name="email" label="Email"><option>a</option></x-form-select>', '<x-form-textarea name="email" label="Email" />'] as $component) {
            $this->withViewErrors(['email' => 'Enter a real email.'])
                ->blade($component)
                ->assertSee('aria-invalid="true"', false)
                ->assertSee('aria-describedby="email-error"', false)
                ->assertSee('<p id="email-error" class="mt-1 text-sm text-error-text">Enter a real email.</p>', false);
        }
    }

    public function test_a_field_without_an_error_says_nothing_and_keeps_its_own_description(): void
    {
        $this->withViewErrors([])
            ->blade('<x-form-input name="email" label="Email" aria-describedby="email-hint" />')
            ->assertDontSee('aria-invalid', false)
            ->assertSee('aria-describedby="email-hint"', false)
            ->assertDontSee('email-error', false);

        $this->withViewErrors(['email' => 'Enter a real email.'])
            ->blade('<x-form-input name="email" label="Email" id="work-email" aria-describedby="email-hint" />')
            ->assertSee('aria-describedby="email-hint work-email-error"', false)
            ->assertSee('id="work-email-error"', false);
    }

    /** @return array<string, array{string, string, list<string>}> */
    public static function authForms(): array
    {
        return [
            'login' => ['login', 'login', ['password']],
            'register' => ['register', 'register', ['first_name', 'last_name', 'email', 'password']],
            'forgot password' => ['password.request', 'password.email', ['email']],
        ];
    }

    #[DataProvider('authForms')]
    public function test_an_auth_page_shows_each_error_by_its_field(string $page, string $action, array $fields): void
    {
        if (! Route::has($page)) {
            $this->markTestSkipped("{$page} is switched off in this app");
        }

        $this->from(route($page))->post(route($action), ['email' => '']);

        $html = $this->get(route($page))->getContent();

        foreach ($fields as $field) {
            $this->assertStringContainsString('aria-describedby="'.$field.'-error"', $html, "{$page}: {$field}");
            $this->assertStringContainsString('id="'.$field.'-error"', $html, "{$page}: {$field}");
        }
    }

    public function test_the_other_auth_views_tie_their_fields_to_their_errors(): void
    {
        foreach (['auth.reset-password' => ['password', 'password_confirmation'], 'auth.confirm-password' => ['password'], 'auth.two-factor-challenge' => ['code', 'recovery_code']] as $view => $fields) {
            $source = file_get_contents(resource_path('views/'.str_replace('.', '/', $view).'.blade.php'));

            foreach ($fields as $field) {
                $this->assertStringContainsString("@error('{$field}') aria-invalid=\"true\" aria-describedby=\"{$field}-error\" @enderror", $source, "{$view}: {$field}");
                $this->assertStringContainsString("<x-form-error for=\"{$field}\"", $source, "{$view}: {$field}");
            }
        }
    }

    public function test_each_error_shows_once_and_a_failed_sign_in_is_a_message_about_the_attempt(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);
        $this->from(route('login'))->post(route('login'), ['email' => 'jane@example.com', 'password' => 'wrong']);

        $html = $this->get(route('login'))->getContent();
        $message = __('auth.failed');
        $this->assertSame(1, substr_count($html, e($message)), 'shown once');
        // It's about the attempt, not the address: in the box at the top, with the email field left alone.
        $this->assertMatchesRegularExpression('#role="alert"[^>]*>.*?'.preg_quote(e($message), '#').'#s', $html);
        $this->assertStringNotContainsString('email-error', $html);

        $this->withViewErrors(['email' => 'Under the field.', 'throttle' => 'Too many tries.'])
            ->blade('<x-form-errors :fields="[\'email\']" />')
            ->assertSee('Too many tries.')
            ->assertDontSee('Under the field.');

        $this->withViewErrors(['email' => 'Under the field.'])
            ->blade('<x-form-errors :fields="[\'email\']" />')
            ->assertDontSee('role="alert"', false);
    }

    public function test_an_error_in_a_named_bag_is_shown_by_its_field(): void
    {
        $this->withViewErrors(['code' => 'Wrong code.'], 'confirm')
            ->blade('<x-form-error for="code" bag="confirm" /><x-form-error for="code" />')
            ->assertSee('<p id="code-error" class="mt-1 text-sm text-error-text">Wrong code.</p>', false);

        $this->assertSame(1, substr_count((string) $this->withViewErrors(['code' => 'Wrong code.'], 'confirm')->blade('<x-form-error for="code" bag="confirm" /><x-form-error for="code" />'), 'Wrong code.'), 'the default bag has nothing to show');
    }
}
