<?php

declare(strict_types=1);

use App\Http\Controllers\Console\SupportBotConsoleController;
use Illuminate\Support\Facades\Route;

/*
 * قسم «The Bot» في اللوحة.
 *
 * الحارس على كل مسار هو support_bot.manage عدا نصّ المحادثة، فقراءة ما كتبه
 * المستخدمون صلاحية منفصلة: من يضبط نصوص البوت ليس بالضرورة من يجوز له قراءة
 * محادثات الناس.
 */
Route::prefix('bot')->name('bot.')->group(function (): void {
    Route::get('/', [SupportBotConsoleController::class, 'index'])
        ->middleware('can:support_bot.manage')
        ->name('index');

    Route::post('toggle', [SupportBotConsoleController::class, 'toggle'])
        ->middleware('can:support_bot.manage')
        ->name('toggle');

    Route::post('audiences', [SupportBotConsoleController::class, 'audiences'])
        ->middleware('can:support_bot.manage')
        ->name('audiences');

    Route::post('entry', [SupportBotConsoleController::class, 'entry'])
        ->middleware('can:support_bot.manage')
        ->name('entry');

    Route::post('rule', [SupportBotConsoleController::class, 'rule'])
        ->middleware('can:support_bot.manage')
        ->name('rule');

    Route::post('access', [SupportBotConsoleController::class, 'access'])
        ->middleware('can:support_bot.manage')
        ->name('access');

    Route::get('conversations/{conversation}', [SupportBotConsoleController::class, 'conversation'])
        ->middleware('can:support_bot.archive.view')
        ->whereUlid('conversation')
        ->name('conversation');
});
