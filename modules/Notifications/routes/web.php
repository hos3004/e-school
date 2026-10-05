<?php

declare(strict_types=1);

/*
 | نقاط النافذة المنبثقة — مصادقة جلسة، بلا تسجيل لأي شيء لغير المصادق.
 */

use App\Http\Controllers\Portal\PopupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session'])->group(function (): void {
    Route::get('/popups/active', [PopupController::class, 'active'])
        ->name('popups.active');

    Route::post('/popups/{campaign}/{interaction}', [PopupController::class, 'interact'])
        ->whereUlid('campaign')
        ->whereIn('interaction', ['impression', 'dismiss', 'acknowledge', 'click'])
        ->name('popups.interact');
});
