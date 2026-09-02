<?php

declare(strict_types=1);

namespace App\Support\Panels\Contracts;

/** Layout slot a panel renders into on a Show/Edit page. */
enum PanelRegion: string
{
    case Main = 'main';
    case Sidebar = 'sidebar';
}
