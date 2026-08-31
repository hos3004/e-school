<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Recordings\Presentation\Http\Controllers\DeleteRecordingController;
use Modules\Recordings\Presentation\Http\Controllers\ListRecordingsController;
use Modules\Recordings\Presentation\Http\Controllers\LogRecordingViewController;
use Modules\Recordings\Presentation\Http\Controllers\MarkRecordingReadyController;
use Modules\Recordings\Presentation\Http\Controllers\ShowRecordingController;
use Modules\Recordings\Presentation\Http\Controllers\StoreRecordingController;

/*
|--------------------------------------------------------------------------
| مسارات موديول Recordings — الـ API
|--------------------------------------------------------------------------
|
| تُحمَّل تلقائيًا ضمن مجموعة «api» بالبادئة api/. كل مسار يمر بسياسة
| RecordingPolicy عبر FormRequest أو Gate::authorize داخل المتحكم.
*/

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/recordings', ListRecordingsController::class)->name('recordings.index');

    Route::post('/recordings', StoreRecordingController::class)
        ->middleware('can:recording.delete')
        ->name('recordings.store');

    Route::get('/recordings/{recording}', ShowRecordingController::class)
        ->whereUlid('recording')
        ->name('recordings.show');

    Route::patch('/recordings/{recording}/ready', MarkRecordingReadyController::class)
        ->whereUlid('recording')
        ->name('recordings.ready');

    Route::post('/recordings/{recording}/views', LogRecordingViewController::class)
        ->whereUlid('recording')
        ->name('recordings.views.log');

    Route::delete('/recordings/{recording}', DeleteRecordingController::class)
        ->whereUlid('recording')
        ->name('recordings.delete');
});
