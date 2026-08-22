<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| مسارات موديول Identity — الـ API
|--------------------------------------------------------------------------
|
| يُحمَّل هذا الملف تلقائيًا من ModuleRegistry::loadRoutes() ضمن مجموعة
| middleware «api» وبالبادئة api/. كل مسارات الكتابة هنا رفيعة:
| Request يتحقق، Controller يستدعي إجراءً، وResource يُخرج.
*/

use Illuminate\Support\Facades\Route;
use Modules\Identity\Presentation\Http\Controllers\ChangeUserStatusController;
use Modules\Identity\Presentation\Http\Controllers\ForgotPasswordController;
use Modules\Identity\Presentation\Http\Controllers\MeController;
use Modules\Identity\Presentation\Http\Controllers\RegisterDeviceController;
use Modules\Identity\Presentation\Http\Controllers\RegisterUserController;
use Modules\Identity\Presentation\Http\Controllers\ResetPasswordController;
use Modules\Identity\Presentation\Http\Controllers\RevokeDeviceController;
use Modules\Identity\Presentation\Http\Controllers\UpdatePasswordController;
use Modules\Identity\Presentation\Http\Controllers\UpdateProfileController;

Route::prefix('identity')->group(function (): void {
    // ── عام (زوّار) ──────────────────────────────────────────────
    Route::middleware('guest')->group(function (): void {
        Route::post('register', RegisterUserController::class)->name('identity.register');
        Route::post('forgot-password', ForgotPasswordController::class)->name('identity.password.email');
        Route::post('reset-password', ResetPasswordController::class)->name('identity.password.reset');
    });

    // ── مصادَق عليه ──────────────────────────────────────────────
    Route::middleware('auth')->group(function (): void {
        Route::get('me', MeController::class)->name('identity.me');
        Route::patch('me', UpdateProfileController::class)->name('identity.profile.update');
        Route::put('me/password', UpdatePasswordController::class)->name('identity.password.change');

        Route::post('devices', RegisterDeviceController::class)->name('identity.devices.store');
        Route::delete('devices/{device}', RevokeDeviceController::class)
            ->middleware('can:revoke,device')
            ->name('identity.devices.revoke');

        // إدارة حالات الحسابات — عبر Policy وليس فحص أدوار.
        Route::patch('users/{user}/status', ChangeUserStatusController::class)
            ->middleware('can:changeStatus,user')
            ->name('identity.users.change_status');
    });
});
