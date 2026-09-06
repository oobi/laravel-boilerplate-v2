<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserEmailNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_email_trims_and_lowercases(): void
    {
        $this->assertSame('user@example.com', User::normalizeEmail('  User@Example.COM '));
        $this->assertNull(User::normalizeEmail(null));
    }

    public function test_emails_are_stored_normalized_whichever_write_path_sets_them(): void
    {
        $created = User::factory()->create(['email' => 'Mixed.Case@Example.TEST']);
        $this->assertSame('mixed.case@example.test', $created->fresh()->email);

        $updated = User::factory()->create();
        $updated->update(['email' => ' Another.User@Example.TEST ']);
        $this->assertSame('another.user@example.test', $updated->fresh()->email);

        $forced = User::factory()->create();
        $forced->forceFill(['email' => 'Forced@Example.TEST'])->save();
        $this->assertSame('forced@example.test', $forced->fresh()->email);
    }
}
