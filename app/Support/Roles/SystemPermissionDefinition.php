<?php

declare(strict_types=1);

namespace App\Support\Roles;

use BackedEnum;

/**
 * One application-wide permission: a string-backed enum case whose value is
 * the spatie Permission row's name. Core's are App\Enums\SystemPermission; an
 * add-on declares its own enum implementing this and registers it with
 * SystemPermissionRegistry::register() from its service provider, and its
 * cases then behave exactly like core's: listed on the Roles screen, seeded,
 * closed over their implications on every write, checked through the Gate.
 */
interface SystemPermissionDefinition extends BackedEnum
{
    /** The label on the Roles screen. */
    public function label(): string;

    /** The Roles screen group it's listed under; permissions group by this label. */
    public function category(): string;

    /**
     * What it carries with it, one hop: an action carries its area's read
     * floor, a read floor carries panel entry. May name another enum's case
     * (an add-on's read floor carries core's `access admin panel`).
     *
     * @return list<SystemPermissionDefinition>
     */
    public function implies(): array;
}
