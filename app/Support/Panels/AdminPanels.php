<?php

declare(strict_types=1);

namespace App\Support\Panels;

use App\Panels\Users\SecurityPanel;
use App\Panels\Users\StatisticsPanel;
use App\Panels\Users\UserInformationFormSection;
use App\Support\Panels\Registry\PanelRegistry;

/** The application's curated admin page panels — edit this file to add, remove or reorder panels. */
class AdminPanels
{
    public static function define(): void
    {
        PanelRegistry::for('users.show')->add(
            StatisticsPanel::class,
            SecurityPanel::class,
        );

        PanelRegistry::for('users.edit')->add(
            UserInformationFormSection::class,
        );
    }
}
