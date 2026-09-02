<?php

use App\Providers\AppServiceProvider;
use App\Providers\NavigationServiceProvider;
use App\Providers\PanelExtensionDemoServiceProvider;
use App\Providers\PanelsServiceProvider;

return [
    AppServiceProvider::class,
    NavigationServiceProvider::class,
    PanelsServiceProvider::class,
    PanelExtensionDemoServiceProvider::class,
];
