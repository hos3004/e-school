<?php

declare(strict_types=1);
use App\Http\Controllers\Console\FollowupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:student.view.any', 'can:enrollment.view', 'can:attendance.view', 'can:discipline.view_any'])->group(function (): void {
    Route::post('/followup/{enrollment}/attendance/{attendance}', [FollowupController::class, 'attendance'])->whereUlid('enrollment')->whereUlid('attendance')->middleware('can:attendance.override')->name('followup.attendance');
    Route::get('/followup', [FollowupController::class, 'index'])->name('followup');
    Route::post('/followup/{enrollment}', [FollowupController::class, 'update'])->whereUlid('enrollment')->name('followup.update');
});
