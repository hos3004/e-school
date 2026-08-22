<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Presentation\Http\Controllers\AddQuestionController;
use Modules\Assessments\Presentation\Http\Controllers\ArchiveAssessmentController;
use Modules\Assessments\Presentation\Http\Controllers\GradeAttemptController;
use Modules\Assessments\Presentation\Http\Controllers\ListAssessmentsController;
use Modules\Assessments\Presentation\Http\Controllers\RemoveQuestionController;
use Modules\Assessments\Presentation\Http\Controllers\ShowAssessmentController;
use Modules\Assessments\Presentation\Http\Controllers\StartAttemptController;
use Modules\Assessments\Presentation\Http\Controllers\StoreAssessmentController;
use Modules\Assessments\Presentation\Http\Controllers\SubmitAttemptController;
use Modules\Assessments\Presentation\Http\Controllers\UpdateAssessmentController;

Route::get('assessments', ListAssessmentsController::class)->name('assessments.index');
Route::get('assessments/{assessment}', ShowAssessmentController::class)->name('assessments.show');

Route::post('assessments', StoreAssessmentController::class)
    ->middleware('can:create,'.Assessment::class)
    ->name('assessments.store');

Route::prefix('assessments/{assessment}')->group(function (): void {
    Route::patch('/', UpdateAssessmentController::class)->name('assessments.update');
    Route::delete('/', ArchiveAssessmentController::class)->name('assessments.archive');
    Route::post('questions', AddQuestionController::class)->name('assessments.questions.store');
    Route::post('attempts', StartAttemptController::class)->name('assessments.attempts.start');
});

Route::prefix('questions/{question}')->group(function (): void {
    Route::delete('/', RemoveQuestionController::class)->name('assessments.questions.destroy');
});

Route::prefix('attempts/{attempt}')->group(function (): void {
    Route::post('submit', SubmitAttemptController::class)->name('assessments.attempts.submit');
    Route::post('grade', GradeAttemptController::class)->name('assessments.attempts.grade');
});
