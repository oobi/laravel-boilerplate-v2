<?php

declare(strict_types=1);
use App\Panels\Users\SecurityPanel;
use App\Panels\Users\StatisticsPanel;
use App\Panels\Users\UserInformationFormSection;

/**
 * Panels registered per resource/page key (e.g. "users.show"). Add-ons
 * (like a future Teams tier) should NOT edit this file — instead call
 * App\Support\Panels\PanelRegistry::extend() from their own service
 * provider's boot() method. See docs/architecture.md.
 */
return [
    'users' => [
        'show' => [
            StatisticsPanel::class,
            SecurityPanel::class,
        ],
        'edit' => [
            UserInformationFormSection::class,
        ],
    ],
];
