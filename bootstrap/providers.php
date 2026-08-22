<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Support\SupportServiceProvider;
use Shared\Module\ModuleServiceProvider;

return [
    // Alpine يُحمَّل من حزمة support — تسجيلها أولًا يضمن أن
    // بقية حزم Filament تُسجّل مكوّناتها قبل بدء Alpine.
    SupportServiceProvider::class,
    AppServiceProvider::class,
    AdminPanelProvider::class,
    ModuleServiceProvider::class,
];
