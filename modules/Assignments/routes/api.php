<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Assignments\Presentation\Http\Controllers\CreateAssignmentController;
use Modules\Assignments\Presentation\Http\Controllers\GradeSubmissionController;
use Modules\Assignments\Presentation\Http\Controllers\ListAssignmentsController;
use Modules\Assignments\Presentation\Http\Controllers\ShowAssignmentController;
use Modules\Assignments\Presentation\Http\Controllers\SubmitAssignmentController;
use Modules\Assignments\Presentation\Http\Controllers\SubmitOwnAssignmentController;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('assignments', ListAssignmentsController::class)->name('assignments.index');
    Route::post('assignments', CreateAssignmentController::class)->name('assignments.store');
    Route::get('assignments/{assignment}', ShowAssignmentController::class)->name('assignments.show');
    Route::post('assignments/{assignment}/submit', SubmitOwnAssignmentController::class)
        ->name('assignments.submit-own');
    Route::post('assignment-submissions/{submission}/submit', SubmitAssignmentController::class)
        ->name('assignment-submissions.submit');
    Route::post('assignment-submissions/{submission}/grade', GradeSubmissionController::class)
        ->name('assignment-submissions.grade');
});
