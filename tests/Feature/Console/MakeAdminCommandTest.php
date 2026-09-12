<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Database\Seeders\SystemRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MakeAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_super_admin_with_a_normalized_email(): void
    {
        $this->artisan('bp:make-admin')
            ->expectsQuestion('First name', 'Ada')
            ->expectsQuestion('Last name', 'Lovelace')
            ->expectsQuestion('Email', ' Ada@Example.TEST ')
            ->expectsQuestion('Password', 'password')
            ->assertSuccessful();

        $admin = User::sole();
        $this->assertSame('ada@example.test', $admin->email);
        $this->assertSame('Ada Lovelace', $admin->name);
        $this->assertTrue($admin->is_super_admin);
    }

    public function test_administrator_puts_the_user_on_the_seeded_role_without_the_super_admin_flag(): void
    {
        (new SystemRolesSeeder)->run();

        $this->artisan('bp:make-admin', ['--administrator' => true])
            ->expectsQuestion('First name', 'Ada')
            ->expectsQuestion('Last name', 'Lovelace')
            ->expectsQuestion('Email', 'ada@example.test')
            ->expectsQuestion('Password', 'password')
            ->expectsOutputToContain('Administrator created')
            ->assertSuccessful();

        $admin = User::sole();
        $this->assertFalse($admin->is_super_admin);
        $this->assertTrue($admin->hasRole(SystemRolesSeeder::ADMINISTRATOR));
        $this->assertTrue($admin->canAccessAdmin());
    }

    public function test_administrator_fails_clearly_when_the_role_has_not_been_seeded(): void
    {
        $this->artisan('bp:make-admin', ['--administrator' => true])
            ->expectsOutputToContain("No 'Administrator' system role exists")
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_a_second_super_admin_is_created_with_a_warning(): void
    {
        User::factory()->superAdmin()->create();

        $this->artisan('bp:make-admin')
            ->expectsOutputToContain('A super admin already exists')
            ->expectsQuestion('First name', 'Ada')
            ->expectsQuestion('Last name', 'Lovelace')
            ->expectsQuestion('Email', 'ada@example.test')
            ->expectsQuestion('Password', 'password')
            ->assertSuccessful();

        $this->assertSame(2, User::query()->where('is_super_admin', true)->count());
    }

    public function test_it_rejects_an_email_already_taken_in_another_casing(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->artisan('bp:make-admin')
            ->expectsQuestion('First name', 'Ada')
            ->expectsQuestion('Last name', 'Lovelace')
            ->expectsQuestion('Email', 'TAKEN@Example.com')
            ->assertFailed();

        $this->assertSame(1, User::count());
    }
}
