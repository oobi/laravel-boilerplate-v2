<?php

declare(strict_types=1);

namespace App\Support\Navigation;

use App\Enums\SystemGate;
use App\Enums\SystemPermission;
use App\Support\Navigation\Registry\NavItem;
use App\Support\Navigation\Registry\NavRegistry;

/** The application's curated admin sidebar menu — edit this file to add, remove or reorder nav. */
class AdminNav
{
    public static function define(): void
    {
        NavRegistry::item('dashboard')
            ->label(__('Dashboard'))
            ->route('dashboard')
            ->icon('heroicon-o-squares-2x2')
            ->order(0);

        // Ships empty: add-ons (e.g. the teams tier) add to it, and an empty group is never rendered.
        NavRegistry::group('business')
            ->label(__('Business'))
            ->can(SystemPermission::ACCESS_ADMIN_PANEL)
            ->order(10);

        NavRegistry::group('people-access')
            ->label(__('People & Access'))
            ->can(SystemPermission::ACCESS_ADMIN_PANEL)
            ->order(20)
            ->add(
                NavItem::make('users')
                    ->label(__('Users'))
                    ->route('users.index')
                    ->icon('heroicon-o-user')
                    ->active('users.*')
                    ->can(SystemPermission::VIEW_USERS),
                NavItem::make('roles')
                    ->label(__('Roles'))
                    ->route('roles.index')
                    ->icon('heroicon-o-shield-check')
                    ->active('roles.*')
                    ->can(SystemGate::MANAGE_ROLES),
            );
    }
}
