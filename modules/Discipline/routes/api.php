<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| مسارات موديول Discipline — الـ API
|--------------------------------------------------------------------------
|
| يُحمَّل هذا الملف تلقائيًا من ModuleRegistry::loadRoutes() ضمن مجموعة
| middleware «api» وبالبادئة api/ عند إقلاع التطبيق.
*/

use Illuminate\Support\Facades\Route;
use Modules\Discipline\Presentation\Http\Controllers\CancelReactivationController;
use Modules\Discipline\Presentation\Http\Controllers\DecideReactivationController;
use Modules\Discipline\Presentation\Http\Controllers\ListReactivationRequestsController;
use Modules\Discipline\Presentation\Http\Controllers\ListViolationEventsController;
use Modules\Discipline\Presentation\Http\Controllers\RecordViolationController;
use Modules\Discipline\Presentation\Http\Controllers\RequestReactivationController;
use Modules\Discipline\Presentation\Http\Controllers\ShowReactivationRequestController;
use Modules\Discipline\Presentation\Http\Controllers\ShowViolationController;
use Modules\Discipline\Presentation\Http\Controllers\WaiveViolationController;

Route::middleware(['auth:sanctum'])->group(function (): void {
    // المخالفات — قراءة وتسجيل وعفو.
    Route::get('/discipline/violations', ListViolationEventsController::class)->name('discipline.violations.index');
    Route::post('/discipline/violations', RecordViolationController::class)->name('discipline.violations.store');
    Route::get('/discipline/violations/{violation}', ShowViolationController::class)->name('discipline.violations.show');
    Route::post('/discipline/violations/{violation}/waive', WaiveViolationController::class)->name('discipline.violations.waive');

    // طلبات إعادة التفعيل — تقديم ومراجعة وسحب.
    Route::get('/discipline/reactivations', ListReactivationRequestsController::class)->name('discipline.reactivations.index');
    Route::post('/discipline/reactivations', RequestReactivationController::class)->name('discipline.reactivations.store');
    Route::get('/discipline/reactivations/{reactivation}', ShowReactivationRequestController::class)->name('discipline.reactivations.show');
    Route::patch('/discipline/reactivations/{reactivation}/decision', DecideReactivationController::class)->name('discipline.reactivations.decide');
    Route::delete('/discipline/reactivations/{reactivation}', CancelReactivationController::class)->name('discipline.reactivations.cancel');
});
