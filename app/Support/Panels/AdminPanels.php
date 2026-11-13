<?php

declare(strict_types=1);

namespace App\Support\Panels;

use App\Panels\Settings\OrganisationSection;
use App\Panels\Users\ActivityPanel;
use App\Panels\Users\SecurityPanel;
use App\Panels\Users\StatisticsPanel;
use App\Panels\Users\UserInformationFormSection;
use App\Support\Panels\Registry\PanelRegistry;
use App\Support\Settings\SettingsSections;

/** The application's curated admin page panels and Settings sections: edit this file to add, remove or reorder them. */
class AdminPanels
{
    public static function define(): void
    {
        PanelRegistry::for('users.show')->add(
            StatisticsPanel::class,
            SecurityPanel::class,
        );

        if (config('activitylog.enabled')) {
            PanelRegistry::for('users.show')->add(ActivityPanel::class);
        }

        PanelRegistry::for('users.edit')->add(
            UserInformationFormSection::class,
        );

        SettingsSections::add(OrganisationSection::class);
    }
}
