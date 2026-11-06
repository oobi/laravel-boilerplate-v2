<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuditServiceProvider;
use App\Providers\NavigationServiceProvider;
use App\Providers\PanelsServiceProvider;

return [
    AppServiceProvider::class,
    AuditServiceProvider::class,
    NavigationServiceProvider::class,
    PanelsServiceProvider::class,
];
