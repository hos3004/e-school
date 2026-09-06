<?php

declare(strict_types=1);

use App\Http\Controllers\Console\PlacementController;
use App\Http\Controllers\Console\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:student.create')->group(function (): void {
    Route::get('/registration', [RegistrationController::class, 'index'])->name('registration.index');
    Route::get('/registration/accounts', [RegistrationController::class, 'accounts'])->name('registration.accounts');
    Route::get('/registration/forms/create', [RegistrationController::class, 'create'])->name('registration.forms.create');
    Route::post('/registration/forms', [RegistrationController::class, 'store'])->name('registration.forms.store');
    Route::get('/registration/forms/{form}/edit', [RegistrationController::class, 'edit'])->whereUlid('form')->name('registration.forms.edit');
    Route::patch('/registration/forms/{form}', [RegistrationController::class, 'update'])->whereUlid('form')->name('registration.forms.update');
    Route::get('/registration/applications/{application}', [RegistrationController::class, 'show'])->whereUlid('application')->name('registration.applications.show');
    Route::post('/registration/applications/{application}/decision', [RegistrationController::class, 'decide'])->whereUlid('application')->name('registration.applications.decide');
});

Route::middleware(['can:student.view.any', 'can:enrollment.create', 'can:group.manage'])->group(function (): void {
    Route::get('/placement', [PlacementController::class, 'index'])->name('placement.index');
    Route::post('/placement/preflight', [PlacementController::class, 'preflight'])->name('placement.preflight');
    Route::post('/placement', [PlacementController::class, 'store'])->name('placement.store');
});
