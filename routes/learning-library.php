<?php

declare(strict_types=1);

use App\Http\Controllers\Learning\LearningLibraryController;
use Illuminate\Support\Facades\Route;

// Included inside the existing authenticated, enabled learning. route group.
foreach (['student', 'teacher'] as $kind) {
    Route::get($kind.'/library', [LearningLibraryController::class, 'index'])->defaults('kind', $kind)
        ->middleware('can:content.view')->name($kind.'.library');
    Route::get($kind.'/library/{material}', [LearningLibraryController::class, 'show'])->defaults('kind', $kind)
        ->whereUlid('material')->middleware('can:content.view')->name($kind.'.library.show');
    Route::get($kind.'/library/{material}/open', [LearningLibraryController::class, 'open'])->defaults('kind', $kind)
        ->whereUlid('material')->middleware('can:content.view')->name($kind.'.library.open');
}
