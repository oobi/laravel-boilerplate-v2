<?php

namespace Tests\Feature\Console;

use App\Models\User;
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
