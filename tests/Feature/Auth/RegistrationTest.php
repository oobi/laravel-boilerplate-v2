<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\VerifyEmailResponse;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->skipUnlessFortifyFeature(Features::registration());

        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $this->skipUnlessFortifyFeature(Features::registration());

        $response = $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        // A fresh registrant is sent to where they belong (Destination::home), not the
        // public landing — with the teams tier active and no team yet, that's onboarding
        // (the verified middleware then prompts them to confirm their email).
        $response->assertRedirect(route('team.onboarding'));

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'active' => true,
        ]);
    }

    public function test_verifying_email_lands_the_user_in_the_app_not_the_landing(): void
    {
        // The bound VerifyEmailResponse sends the (now verified) user to where they
        // belong (Destination::home) — for a no-team user, onboarding — not fortify.home.
        $user = User::factory()->create();
        $request = Request::create('/email/verify');
        $request->setUserResolver(fn () => $user);

        $target = app(VerifyEmailResponse::class)->toResponse($request)->getTargetUrl();

        $this->assertStringStartsWith(route('team.onboarding'), $target);
    }

    public function test_registering_with_a_taken_email_fails_validation_regardless_of_casing(): void
    {
        $this->skipUnlessFortifyFeature(Features::registration());

        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'TAKEN@Example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(1, User::count());
    }

    public function test_a_mixed_case_email_is_stored_lowercased(): void
    {
        $this->skipUnlessFortifyFeature(Features::registration());

        $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'Mixed.Case@Example.TEST',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'mixed.case@example.test']);
    }
}
