<?php

use App\AskDocs\AskDocsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AskDocsServiceProvider::class,
    AppServiceProvider::class,
    AdminPanelProvider::class,
];
