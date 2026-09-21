<?php

declare(strict_types=1);

use App\Http\Controllers\Console\ArchiveController;
use Illuminate\Support\Facades\Route;

/*
 * الأرشيف — المُقفل من البرامج والكورسات والمجموعات.
 *
 * لا صلاحية واحدة تحرس الصفحة: من يدير المجموعات وحدها يرى قسمها فقط، والفحص
 * لكل نوع داخل الـcontroller وعلى السجل نفسه عبر Policy. حارس `can:` واحد هنا
 * كان سيحجب نصف الإداريين أو يكشف لهم ما لا يخصهم.
 */
Route::prefix('archive')->name('archive.')->group(function (): void {
    Route::get('/', [ArchiveController::class, 'index'])->name('index');

    Route::whereIn('kind', ['program', 'course', 'group'])->group(function (): void {
        Route::get('{kind}/{id}/preview', [ArchiveController::class, 'preview'])->whereUlid('id')->name('preview');
        Route::post('{kind}/{id}/close', [ArchiveController::class, 'close'])->whereUlid('id')->name('close');
        Route::post('{kind}/{id}/reopen', [ArchiveController::class, 'reopen'])->whereUlid('id')->name('reopen');
    });
});
