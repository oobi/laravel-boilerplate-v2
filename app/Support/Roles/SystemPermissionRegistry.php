<?php

declare(strict_types=1);

namespace App\Support\Roles;

use App\Enums\SystemPermission;
use Illuminate\Support\Collection;
use LogicException;

/**
 * The system permission vocabulary: core's SystemPermission cases, always,
 * then each add-on's registered enum in registration order. Add-ons call
 * register() from their own service provider, the same way NavRegistry and
 * RoleScopeRegistry are extended, so an uninstalled add-on leaves no residue
 * in core. Everything that needs the whole vocabulary (the Roles screen via
 * SystemRoleScope, the seeders, every write path closing a grant over its
 * implications) reads it from here, never from SystemPermission::cases().
 */
final class SystemPermissionRegistry
{
    /** @var list<class-string<SystemPermissionDefinition>> */
    protected static array $enums = [];

    /**
     * Two definitions of one name would be one spatie row with two labels and
     * two implication lists, so a clash is refused rather than merged.
     *
     * @param  class-string<SystemPermissionDefinition>  ...$enums
     */
    public static function register(string ...$enums): void
    {
        foreach ($enums as $enum) {
            if (in_array($enum, self::$enums, true)) {
                continue;
            }

            $taken = self::all()->map(fn (SystemPermissionDefinition $permission): string => $permission->value);
            $clashes = collect($enum::cases())
                ->map(fn (SystemPermissionDefinition $permission): string => $permission->value)
                ->intersect($taken);

            if ($clashes->isNotEmpty()) {
                throw new LogicException("{$enum} redefines system permission(s): {$clashes->implode(', ')}.");
            }

            self::$enums[] = $enum;
        }
    }

    /** Test/console helper: clears add-on registrations (core's cases always remain). */
    public static function flush(): void
    {
        self::$enums = [];
    }

    /** @return Collection<int, SystemPermissionDefinition> */
    public static function all(): Collection
    {
        return collect([SystemPermission::class, ...self::$enums])
            ->flatMap(fn (string $enum): array => $enum::cases())
            ->values();
    }

    public static function find(string $name): ?SystemPermissionDefinition
    {
        return self::all()->first(fn (SystemPermissionDefinition $permission): bool => $permission->value === $name);
    }

    /** @return array<string, list<SystemPermissionDefinition>> category label => its permissions, core's categories first */
    public static function byCategory(): array
    {
        return self::all()
            ->groupBy(fn (SystemPermissionDefinition $permission): string => $permission->category())
            ->map(fn (Collection $permissions): array => $permissions->values()->all())
            ->all();
    }

    /**
     * implies() as the Roles form and the sync command consume it: permission
     * name => the names it carries (one hop; consumers close the chain).
     *
     * @return array<string, list<string>>
     */
    public static function implicationMap(): array
    {
        return self::all()
            ->filter(fn (SystemPermissionDefinition $permission): bool => $permission->implies() !== [])
            ->mapWithKeys(fn (SystemPermissionDefinition $permission): array => [
                $permission->value => collect($permission->implies())
                    ->map(fn (SystemPermissionDefinition $implied): string => $implied->value)
                    ->all(),
            ])
            ->all();
    }

    /**
     * A grant closed over its implications, transitively: what every write
     * path stores (the seeder, the Roles form, UserFactory::withPermission()).
     * Order preserved, no duplicates.
     *
     * @param  list<SystemPermissionDefinition>  $permissions
     * @return list<SystemPermissionDefinition>
     */
    public static function withImplied(array $permissions): array
    {
        return collect(Implications::close(
            self::implicationMap(),
            collect($permissions)->map(fn (SystemPermissionDefinition $permission): string => $permission->value)->all(),
        ))->map(fn (string $name): SystemPermissionDefinition => self::find($name)
            ?? throw new LogicException("System permission [{$name}] is not registered: register its enum before granting or implying it."))->all();
    }
}
