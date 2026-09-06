<?php

declare(strict_types=1);

use App\Http\Controllers\Console\QuranAvailabilityController;
use App\Http\Controllers\Console\QuranController;
use Illuminate\Support\Facades\Route;

Route::patch('/quran/{student}/schedules/{schedule}', [QuranController::class, 'update'])
    ->whereUlid('student')->whereUlid('schedule')
    ->middleware(['can:student.view.any', 'can:schedule.manage'])->name('quran.update');

Route::middleware('can:staff.view')->prefix('/teachers/{teacher}/availability')->whereUlid('teacher')->group(function (): void {
    Route::get('/', [QuranAvailabilityController::class, 'index'])->name('availability.index');
    Route::post('/', [QuranAvailabilityController::class, 'store'])->middleware('can:staff.availability.create')->name('availability.store');
    Route::post('/{availability}/decision', [QuranAvailabilityController::class, 'decide'])->whereUlid('availability')->middleware('can:staff.availability.approve')->name('availability.decide');
    Route::delete('/{availability}', [QuranAvailabilityController::class, 'destroy'])->whereUlid('availability')->middleware('can:staff.contract.update')->name('availability.destroy');
});
