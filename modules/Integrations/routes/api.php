<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| مسارات موديول Integrations — الـ API
|--------------------------------------------------------------------------
|
| يُحمَّل هذا الملف تلقائيًا من ModuleRegistry::loadRoutes() ضمن مجموعة
| middleware «api» وبالبادئة api/ عند إقلاع التطبيق.
*/

use Illuminate\Support\Facades\Route;
use Modules\Integrations\Presentation\Http\Controllers\ActivateConnectionController;
use Modules\Integrations\Presentation\Http\Controllers\DisableConnectionController;
use Modules\Integrations\Presentation\Http\Controllers\RecordDeliveryController;
use Modules\Integrations\Presentation\Http\Controllers\RequeueDeliveryController;
use Modules\Integrations\Presentation\Http\Controllers\SettleDeliveryController;
use Modules\Integrations\Presentation\Http\Controllers\StoreConnectionController;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('integrations/connections', StoreConnectionController::class)
        ->name('integrations.connections.store');

    Route::post('integrations/connections/{connection}/activate', ActivateConnectionController::class)
        ->name('integrations.connections.activate');

    Route::post('integrations/connections/{connection}/disable', DisableConnectionController::class)
        ->name('integrations.connections.disable');

    Route::post('integrations/deliveries', RecordDeliveryController::class)
        ->name('integrations.deliveries.store');

    Route::post('integrations/deliveries/{delivery}/settle', SettleDeliveryController::class)
        ->name('integrations.deliveries.settle');

    Route::post('integrations/deliveries/{delivery}/requeue', RequeueDeliveryController::class)
        ->name('integrations.deliveries.requeue');
});
