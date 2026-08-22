<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Presentation\Http\Controllers\CancelSessionController;
use Modules\Sessions\Presentation\Http\Controllers\CompleteSessionController;
use Modules\Sessions\Presentation\Http\Controllers\ConfirmSessionController;
use Modules\Sessions\Presentation\Http\Controllers\EndSessionController;
use Modules\Sessions\Presentation\Http\Controllers\ExcuseAbsenceController;
use Modules\Sessions\Presentation\Http\Controllers\ListSessionsController;
use Modules\Sessions\Presentation\Http\Controllers\MarkNoShowController;
use Modules\Sessions\Presentation\Http\Controllers\PostponeSessionController;
use Modules\Sessions\Presentation\Http\Controllers\RecordParticipantAttendanceController;
use Modules\Sessions\Presentation\Http\Controllers\ScheduleSessionController;
use Modules\Sessions\Presentation\Http\Controllers\ShowSessionController;
use Modules\Sessions\Presentation\Http\Controllers\StartSessionController;

Route::get('sessions', ListSessionsController::class)->name('sessions.index');
Route::get('sessions/{session}', ShowSessionController::class)->name('sessions.show');

Route::post('sessions', ScheduleSessionController::class)
    ->middleware('can:create,'.Session::class)
    ->name('sessions.store');

Route::prefix('sessions/{session}')->group(function (): void {
    Route::post('confirm', ConfirmSessionController::class)->name('sessions.confirm');
    Route::post('start', StartSessionController::class)->name('sessions.start');
    Route::post('end', EndSessionController::class)->name('sessions.end');
    Route::post('complete', CompleteSessionController::class)->name('sessions.complete');
    Route::post('cancel', CancelSessionController::class)->name('sessions.cancel');
    Route::post('postpone', PostponeSessionController::class)->name('sessions.postpone');
    Route::post('no-show', MarkNoShowController::class)->name('sessions.no-show');
    Route::post('excuse', ExcuseAbsenceController::class)->name('sessions.excuse');
    Route::post('attendance', RecordParticipantAttendanceController::class)->name('sessions.attendance');
});
