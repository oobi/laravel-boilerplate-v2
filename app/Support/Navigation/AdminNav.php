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

        NavRegistry::group('management')
            ->label(__('Management'))
            ->can(SystemPermission::ACCESS_ADMIN_PANEL)
            ->order(10)
            ->add(
                NavItem::make('users')
                    ->label(__('Users'))
                    ->route('users.index')
                    ->icon('heroicon-o-user')
                    ->active('users.*'),
                NavItem::make('roles')
                    ->label(__('Roles'))
                    ->route('roles.index')
                    ->icon('heroicon-o-shield-check')
                    ->active('roles.*')
                    ->can(SystemGate::MANAGE_ROLES),
            );
    }
}
