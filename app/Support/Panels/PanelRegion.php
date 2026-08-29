<?php

declare(strict_types=1);

namespace App\Support\Panels;

/** Layout slot a panel renders into on a Show/Edit page. */
enum PanelRegion: string
{
    case Main = 'main';
    case Sidebar = 'sidebar';
}
