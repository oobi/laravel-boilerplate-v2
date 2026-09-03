<?php

use App\Providers\AppServiceProvider;
use App\Providers\NavigationServiceProvider;
use App\Providers\PanelExtensionDemoServiceProvider;
use App\Providers\PanelsServiceProvider;

return [
    AppServiceProvider::class,
    NavigationServiceProvider::class,
    PanelsServiceProvider::class,

    // demo panel extension provider — delete once the real Teams tier ships its own Team Memberships panel
    // PanelExtensionDemoServiceProvider::class,
];
