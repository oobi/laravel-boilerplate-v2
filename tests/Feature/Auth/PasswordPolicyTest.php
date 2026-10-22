<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Livewire\Admin\Users\CreateUser;
use App\Livewire\Admin\Users\ShowUser;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * One minimum length, `auth.password_min_length`, wherever a password is
 * chosen (GitHub #41). Here at 12, so an 11-character password is refused.
 */
class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    private const SHORT = 'eleven-char';

    private const LONG = 'twelve-chars';

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.password_min_length' => 12]);
    }

    public function test_sign_up_reset_and_profile_follow_it(): void
    {
        $this->skipUnlessFortifyFeature(Features::registration());

        // All three share Fortify's PasswordValidationRules; sign-up is the one without a token or session.
        $attempt = fn (string $password) => $this->post('/register', [
            'first_name' => 'A', 'last_name' => 'B', 'email' => 'new@example.com',
            'password' => $password, 'password_confirmation' => $password,
        ]);

        $attempt(self::SHORT)->assertSessionHasErrors('password');
        $this->assertGuest();

        $attempt(self::LONG)->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }

    /** Each form says the minimum before a password is refused, tied to the field (#41). */
    public function test_the_forms_say_the_minimum(): void
    {
        $hint = __('auth.password_min_hint', ['min' => 12]);

        $this->actingAs(User::factory()->create())->get(route('profile.password'))
            ->assertSee($hint)
            ->assertSeeHtml('minlength="12"')
            ->assertSeeHtml('id="password-hint"');

        Livewire::actingAs(User::factory()->superAdmin()->create())->test(CreateUser::class)
            ->assertSee($hint)
            ->assertSeeHtml('minlength="12"');
    }

    public function test_the_sign_up_form_says_the_minimum(): void
    {
        $this->skipUnlessFortifyFeature(Features::registration());

        $this->get(route('register'))
            ->assertSee(__('auth.password_min_hint', ['min' => 12]))
            ->assertSeeHtml('minlength="12"')
            ->assertSeeHtml('aria-describedby="password-hint');
    }

    public function test_creating_a_user_follows_it(): void
    {
        $page = Livewire::actingAs(User::factory()->superAdmin()->create())->test(CreateUser::class)
            ->set('data.first_name', 'Brand')
            ->set('data.last_name', 'New')
            ->set('data.email', 'brand-new@example.com');

        $page->set('data.password', self::SHORT)->set('data.password_confirmation', self::SHORT)
            ->call('create')
            ->assertHasFormErrors(['password']);

        $page->set('data.password', self::LONG)->set('data.password_confirmation', self::LONG)
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_an_admin_setting_a_password_follows_it(): void
    {
        $target = User::factory()->create();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ShowUser::class, ['user' => $target])
            ->callAction('resetPassword', data: ['password' => self::SHORT, 'password_confirmation' => self::SHORT])
            ->assertHasFormErrors(['password']);
    }

    public function test_make_admin_follows_it(): void
    {
        $run = fn (string $password) => $this->artisan('bp:make-admin')
            ->expectsQuestion('First name', 'Ada')
            ->expectsQuestion('Last name', 'Lovelace')
            ->expectsQuestion('Email', 'ada@example.test')
            ->expectsQuestion('Password', $password);

        // Outside a terminal the prompt can't ask again, so a refused password ends the run.
        $run(self::SHORT)->assertFailed();
        $this->assertDatabaseCount('users', 0);

        $run(self::LONG)->assertSuccessful();
        $this->assertDatabaseCount('users', 1);
    }

    /** @return array<string, array{?string, ?string, int}> */
    public static function defaults(): array
    {
        return [
            'production, unset' => [null, 'production', 12],
            'no APP_ENV counts as production' => [null, null, 12],
            'local, unset' => [null, 'local', 8],
            'set explicitly' => ['16', 'local', 16],
            'blank falls back to the default' => ['', 'production', 12],
            'not a number falls back to the default' => ['twelve', 'production', 12],
            'never below 8' => ['4', 'local', 8],
        ];
    }

    #[DataProvider('defaults')]
    public function test_the_setting_defaults_by_environment(?string $setting, ?string $environment, int $expected): void
    {
        $this->assertSame($expected, $this->withEnv(
            ['AUTH_PASSWORD_MIN_LENGTH' => $setting, 'APP_ENV' => $environment],
            fn (): int => (require config_path('auth.php'))['password_min_length'],
        ));
    }

    /**
     * Run $callback with these env values (null: unset), restoring all three places
     * env() reads: $_SERVER, $_ENV and getenv() (where phpunit.xml puts APP_ENV).
     *
     * @param  array<string, ?string>  $values
     */
    private function withEnv(array $values, Closure $callback): mixed
    {
        $read = fn (string $key): array => [$_SERVER[$key] ?? null, $_ENV[$key] ?? null, getenv($key) === false ? null : getenv($key)];
        $write = function (string $key, ?string $server, ?string $env, ?string $process): void {
            if ($server === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $server;
            }

            if ($env === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $env;
            }

            putenv($process === null ? $key : "{$key}={$process}");
        };

        $saved = collect($values)->map(fn ($value, string $key): array => $read($key))->all();
        collect($values)->each(fn (?string $value, string $key) => $write($key, $value, $value, $value));

        try {
            return $callback();
        } finally {
            collect($saved)->each(fn (array $previous, string $key) => $write($key, ...$previous));
        }
    }
}
