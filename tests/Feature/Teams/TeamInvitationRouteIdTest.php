<?php

namespace Tests\Feature\Teams;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * An invitation link with a junk id is a 404 for anyone, signed in or not.
 * The invitation is bound before `signed` checks the link, so on Postgres a
 * guest could turn one into a 500 (GitHub oobi#89). Run on Postgres to see the
 * difference; SQLite returns 404 either way.
 */
class TeamInvitationRouteIdTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function junkIds(): array
    {
        $routes = [
            'accept' => ['get', 'team.invitations.accept'],
            'register form' => ['get', 'team.invitations.register'],
            'register' => ['post', 'team.invitations.register.store'],
        ];

        $cases = [];

        foreach ($routes as $label => [$method, $routeName]) {
            foreach (['letters' => 'abc', 'decimal' => '1.5', 'past bigint' => '99999999999999999999'] as $idLabel => $id) {
                $cases["{$label}, {$idLabel}"] = [$method, $routeName, $id];
            }
        }

        return $cases;
    }

    #[DataProvider('junkIds')]
    public function test_a_junk_invitation_id_is_not_found(string $method, string $routeName, string $id): void
    {
        $this->{$method}(route($routeName, ['invitation' => $id]))->assertNotFound();
    }
}
