<?php

declare(strict_types=1);

use App\Http\Controllers\Console\TeacherDuesController;
use App\Http\Controllers\Console\TeacherDuesWriteController;
use Illuminate\Support\Facades\Route;

Route::get('/teacher-dues', TeacherDuesController::class)->middleware('can:payroll.view')->name('teacher-dues.index');
Route::post('/teacher-dues/periods/{period}/adjustments', [TeacherDuesWriteController::class, 'propose'])
    ->whereUlid('period')->middleware(['can:payroll.view', 'can:'.config('payroll.adjustments.propose_permission')])->name('teacher-dues.propose');
foreach (['approve', 'reject'] as $decision) {
    Route::post('/teacher-dues/adjustments/{adjustment}/'.$decision, [TeacherDuesWriteController::class, 'decide'])
        ->whereUlid('adjustment')->defaults('decision', $decision)
        ->middleware(['can:payroll.view', 'can:'.config('payroll.adjustments.approve_permission')])->name('teacher-dues.'.$decision);
}
