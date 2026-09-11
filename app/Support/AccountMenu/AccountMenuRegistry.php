<?php

declare(strict_types=1);

namespace App\Support\AccountMenu;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

/**
 * The header account-menu registry — the seam for add-ons to add links to the
 * avatar dropdown (e.g. the teams tier's cross-area "Teams" / "Admin dashboard"
 * links) from their own service provider, without editing the core
 * header-menu view. Mirrors NavRegistry/PanelRegistry: core renders whatever
 * is registered, so an uninstalled add-on leaves no residue.
 */
class AccountMenuRegistry
{
    /** @var array<string, AccountMenuItem> */
    protected static array $items = [];

    /** Fetch-or-create a link by name, so re-registration on re-boot is idempotent. */
    public static function item(string $name): AccountMenuItem
    {
        return static::$items[$name] ??= new AccountMenuItem($name);
    }

    /** Test/console helper — clears runtime registrations. */
    public static function flush(): void
    {
        static::$items = [];
    }

    /** @return Collection<int, AccountMenuItem> */
    public static function visible(?Authenticatable $viewer): Collection
    {
        return collect(static::$items)
            ->filter(fn (AccountMenuItem $item): bool => $item->isVisible($viewer))
            ->sortBy(fn (AccountMenuItem $item): int => $item->getOrder())
            ->values();
    }
}
