<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Auth\ExpiredForms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A guest auth form submitted after its session expired goes back to the form
 * with a notice, not to the 419 page (App\Support\Auth\ExpiredForms).
 */
class ExpiredFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enforceCsrfTokens();
    }

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function formsThatReturnToThemselves(): array
    {
        return [
            'login' => ['/login', null],
            'register' => ['/register', Features::registration()],
            'forgot password' => ['/forgot-password', Features::resetPasswords()],
        ];
    }

    #[DataProvider('formsThatReturnToThemselves')]
    public function test_a_stale_form_goes_back_to_itself_with_a_notice_and_the_email(string $uri, ?string $feature): void
    {
        if ($feature !== null) {
            $this->skipUnlessFortifyFeature($feature);
        }

        $this->post($uri, [
            '_token' => 'stale',
            'email' => 'someone@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])
            ->assertRedirect($uri)
            ->assertSessionHas(ExpiredForms::FLASH_KEY, true)
            ->assertSessionHasInput('email', 'someone@example.com')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation')
            ->assertSessionMissing('_old_input._token');

        $this->get($uri)
            ->assertOk()
            ->assertSeeText(__('auth.page_expired'))
            ->assertSee('value="someone@example.com"', false);
    }

    public function test_a_stale_login_keeps_remember_me_ticked(): void
    {
        $this->post('/login', ['_token' => 'stale', 'email' => 'someone@example.com', 'remember' => 'on'])
            ->assertRedirect('/login')
            ->assertSessionHasInput('remember', 'on');

        $this->assertMatchesRegularExpression('/<input[^>]*name="remember"[^>]*\bchecked\b/', $this->get('/login')->getContent());
    }

    public function test_a_stale_password_confirmation_goes_back_to_its_form(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/user/confirm-password', ['_token' => 'stale', 'password' => 'secret-password'])
            ->assertRedirect('/user/confirm-password')
            ->assertSessionHas(ExpiredForms::FLASH_KEY, true)
            ->assertSessionMissing('_old_input.password');
    }

    public function test_the_notice_shows_only_after_an_expired_form(): void
    {
        $this->get('/login')->assertOk()->assertDontSeeText(__('auth.page_expired'));
    }

    public function test_a_stale_password_reset_goes_back_to_its_emailed_link(): void
    {
        $this->skipUnlessFortifyFeature(Features::resetPasswords());

        $this->post('/reset-password', [
            '_token' => 'stale',
            'token' => 'reset-token',
            'email' => 'someone@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])
            ->assertRedirect(route('password.reset', ['token' => 'reset-token', 'email' => 'someone@example.com']))
            ->assertSessionHas(ExpiredForms::FLASH_KEY, true)
            ->assertSessionMissing('_old_input.token')
            ->assertSessionMissing('_old_input.password');
    }

    public function test_a_stale_password_reset_without_its_token_keeps_the_419_page(): void
    {
        $this->skipUnlessFortifyFeature(Features::resetPasswords());

        $this->post('/reset-password', ['_token' => 'stale', 'email' => 'someone@example.com'])
            ->assertStatus(419);
    }

    public function test_a_stale_two_factor_challenge_starts_again_from_login(): void
    {
        $this->skipUnlessFortifyFeature(Features::twoFactorAuthentication());

        $this->post('/two-factor-challenge', ['_token' => 'stale', 'code' => '123456'])
            ->assertRedirect(route('login'))
            ->assertSessionHas(ExpiredForms::FLASH_KEY, true)
            ->assertSessionMissing('_old_input.code');
    }

    public function test_a_json_request_still_gets_a_419(): void
    {
        $this->postJson('/login', ['_token' => 'stale', 'email' => 'someone@example.com'])
            ->assertStatus(419);
    }

    public function test_a_form_that_is_not_listed_keeps_the_419_page(): void
    {
        $this->post('/logout', ['_token' => 'stale'])
            ->assertStatus(419)
            ->assertSeeText(__('Page expired'));
    }

    public function test_an_add_on_can_list_its_own_form(): void
    {
        ExpiredForms::register('logout', fn (): string => route('login'));

        $this->post('/logout', ['_token' => 'stale'])
            ->assertRedirect(route('login'))
            ->assertSessionHas(ExpiredForms::FLASH_KEY, true);
    }
}
