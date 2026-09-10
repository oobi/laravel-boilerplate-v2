<?php

declare(strict_types=1);

namespace App\Support\Roles;

use Illuminate\Support\Collection;

/**
 * The RoleScope classes registered for the admin Roles screen — the app
 * (App\Support\Roles\AdminRoleScopes) and add-ons (from their own service
 * provider) both call register(), the same way NavRegistry and PanelRegistry
 * are extended. One tab renders per registered scope; with a single scope the
 * screen shows no tabs at all.
 */
final class RoleScopeRegistry
{
    /** @var list<class-string<RoleScope>> */
    protected static array $classes = [];

    /** @param  class-string<RoleScope>  ...$classes */
    public static function register(string ...$classes): void
    {
        foreach ($classes as $class) {
            if (! in_array($class, self::$classes, true)) {
                self::$classes[] = $class;
            }
        }
    }

    /** Test/console helper — clears runtime registrations. */
    public static function flush(): void
    {
        self::$classes = [];
    }

    /** @return Collection<int, RoleScope> */
    public static function all(): Collection
    {
        return collect(self::$classes)
            ->map(fn (string $class): RoleScope => app($class))
            ->sortBy(fn (RoleScope $scope): int => $scope->order())
            ->values();
    }

    public static function find(?string $key): ?RoleScope
    {
        return self::all()->first(fn (RoleScope $scope): bool => $scope->key() === $key);
    }

    /** The scope the bare Roles route opens on: the lowest-ordered one. */
    public static function default(): RoleScope
    {
        return self::all()->firstOrFail();
    }

    /**
     * The route parameters that open a scope's tab — none for the default
     * scope, so a single-scope install keeps its plain `/admin/roles` URLs.
     *
     * @return array<string, string>
     */
    public static function routeParameters(RoleScope $scope): array
    {
        return $scope->key() === self::default()->key() ? [] : ['scope' => $scope->key()];
    }
}
