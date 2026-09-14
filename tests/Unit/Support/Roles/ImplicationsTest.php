<?php

namespace Tests\Unit\Support\Roles;

use App\Support\Roles\Implications;
use PHPUnit\Framework\TestCase;

class ImplicationsTest extends TestCase
{
    /** @var array<string, list<string>> */
    private const MAP = [
        'delete users' => ['view users'],
        'manage users' => ['view users'],
        'view users' => ['access admin panel'],
        'a' => ['b'],
        'b' => ['a'],
    ];

    public function test_close_follows_the_chain_in_order_without_duplicates(): void
    {
        $this->assertSame(
            ['delete users', 'manage users', 'view users', 'access admin panel'],
            Implications::close(self::MAP, ['delete users', 'manage users']),
        );
    }

    public function test_close_keeps_names_the_map_does_not_know(): void
    {
        $this->assertSame(['custom', 'view users', 'access admin panel'], Implications::close(self::MAP, ['custom', 'view users']));
    }

    public function test_close_terminates_on_a_cycle(): void
    {
        $this->assertSame(['a', 'b'], Implications::close(self::MAP, ['a']));
    }

    public function test_is_carried_asks_whether_another_selected_name_carries_this_one(): void
    {
        // The ticked selection already contains what it carries; the question is who else needs it.
        $selection = ['access admin panel', 'view users', 'delete users'];

        $this->assertTrue(Implications::isCarried(self::MAP, $selection, 'view users'));
        $this->assertTrue(Implications::isCarried(self::MAP, $selection, 'access admin panel'), 'two hops');
        $this->assertFalse(Implications::isCarried(self::MAP, $selection, 'delete users'));
        $this->assertFalse(Implications::isCarried(self::MAP, ['view users'], 'view users'), 'nothing else ticked frees it');
    }

    public function test_implied_by_is_the_closure_minus_the_selection(): void
    {
        $this->assertSame(['view users', 'access admin panel'], Implications::impliedBy(self::MAP, ['delete users']));
        $this->assertSame([], Implications::impliedBy(self::MAP, ['access admin panel']));
    }
}
