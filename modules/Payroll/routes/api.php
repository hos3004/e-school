<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Payroll\Domain\Models\PayrollAdjustment;
use Modules\Payroll\Presentation\Http\Controllers\ApprovePayrollAdjustmentController;
use Modules\Payroll\Presentation\Http\Controllers\ListPayrollEntriesController;
use Modules\Payroll\Presentation\Http\Controllers\ListPayrollPeriodsController;
use Modules\Payroll\Presentation\Http\Controllers\ProposePayrollAdjustmentController;
use Modules\Payroll\Presentation\Http\Controllers\RejectPayrollAdjustmentController;
use Modules\Payroll\Presentation\Http\Controllers\ReleaseDeferredEntriesController;
use Modules\Payroll\Presentation\Http\Controllers\ShowPayrollPeriodController;

if (!(bool) config('features.payroll')) {
    return;
}

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('payroll/periods', ListPayrollPeriodsController::class)
        ->name('payroll.periods.index');

    Route::get('payroll/periods/{period}', ShowPayrollPeriodController::class)
        ->name('payroll.periods.show');

    Route::get('payroll/entries', ListPayrollEntriesController::class)
        ->name('payroll.entries.index');

    Route::post('payroll/periods/{period}/adjustments', ProposePayrollAdjustmentController::class)
        ->middleware('can:create,'.PayrollAdjustment::class)
        ->name('payroll.adjustments.propose');

    Route::post('payroll/adjustments/{adjustment}/approve', ApprovePayrollAdjustmentController::class)
        ->name('payroll.adjustments.approve');

    Route::post('payroll/adjustments/{adjustment}/reject', RejectPayrollAdjustmentController::class)
        ->name('payroll.adjustments.reject');

    Route::post('payroll/makeup-sessions/{makeupSession}/release-deferred', ReleaseDeferredEntriesController::class)
        ->name('payroll.deferred.release');
});
