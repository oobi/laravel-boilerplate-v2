<?php

namespace Tests\Unit\Support;

use App\Support\Navigation\Registry\NavGroup;
use App\Support\Navigation\Registry\NavItem;
use PHPUnit\Framework\TestCase;

class NavGroupTest extends TestCase
{
    public function test_adding_an_item_again_replaces_it_in_place_instead_of_duplicating_it(): void
    {
        $group = (new NavGroup('management'))
            ->add(NavItem::make('members')->label('Members'), NavItem::make('settings'));

        $group->add(NavItem::make('members')->label('Staff'));

        $this->assertSame(['members', 'settings'], array_column($group->items, 'name'));
        $this->assertSame('Staff', $group->items[0]->label);
    }
}
