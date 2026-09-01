<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Messaging\Presentation\Http\Controllers\PortalMessagingController;

Route::middleware(['auth', 'auth.session', 'can:message.send'])->group(function (): void {
    Route::get('/messages', [PortalMessagingController::class, 'index'])
        ->name('portal.messaging.index');
    Route::get('/messages/create', [PortalMessagingController::class, 'create'])
        ->name('portal.messaging.create');
    Route::get('/messages/{conversation}', [PortalMessagingController::class, 'show'])
        ->whereUlid('conversation')
        ->name('portal.messaging.show');
});
