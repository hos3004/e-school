<?php

declare(strict_types=1);

use App\Http\Controllers\SupportBot\SupportBotChatController;
use Illuminate\Support\Facades\Route;
use Modules\SupportBot\Infrastructure\Providers\SupportBotServiceProvider;

/*
 * محادثة البوت.
 *
 * خارج بادئة manage عمدًا: تلك محروسة بـadmin.panel.access، والمعلم والطالب
 * لا يملكانها. من يملك بوتًا يقرره AccessResolver لا موضع المسار.
 *
 * throttle ليس زينة: لا يوجد throttle عام في هذا التطبيق، وكل رسالة هنا تكلّف
 * مالًا عند المزوّد.
 */
Route::prefix('bot')->name('bot.')->group(function (): void {
    Route::post('ask', [SupportBotChatController::class, 'ask'])
        ->middleware('throttle:'.SupportBotServiceProvider::ASK_LIMITER)
        ->name('ask');

    Route::get('history', [SupportBotChatController::class, 'history'])
        ->name('history');
});
