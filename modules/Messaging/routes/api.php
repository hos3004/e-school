<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Messaging\Presentation\Http\Controllers\FlagMessageController;
use Modules\Messaging\Presentation\Http\Controllers\HandleWhatsappInboundController;
use Modules\Messaging\Presentation\Http\Controllers\ListConversationMessagesController;
use Modules\Messaging\Presentation\Http\Controllers\StoreConversationController;
use Modules\Messaging\Presentation\Http\Controllers\StoreMessageController;
use Modules\Messaging\Presentation\Http\Controllers\StoreWallCommentController;
use Modules\Messaging\Presentation\Http\Controllers\StoreWallPostController;
use Modules\Messaging\Presentation\Http\Controllers\UpdateMessageController;
use Modules\Messaging\Presentation\Http\Controllers\WhatsappWebhookController;

/*
| Webhook الواتساب من مزوّد خارجي — بلا مصادقة جلسة، التحقق عبر المدخلات.
*/
Route::post('webhooks/whatsapp', WhatsappWebhookController::class)->name('whatsapp.webhook');

Route::post('conversations', StoreConversationController::class)->name('conversations.store');
Route::get('conversations/{conversation}/messages', ListConversationMessagesController::class)
    ->name('conversations.messages.index');
Route::post('conversations/{conversation}/messages', StoreMessageController::class)
    ->name('conversations.messages.store');

Route::prefix('messages/{message}')->group(function (): void {
    Route::put('', UpdateMessageController::class)->name('messages.update');
    Route::post('flag', FlagMessageController::class)->name('messages.flag');
});

Route::post('wall/posts', StoreWallPostController::class)->name('wall.posts.store');
Route::post('wall/posts/{post}/comments', StoreWallCommentController::class)->name('wall.posts.comments.store');

Route::post('whatsapp/inbound/{inbound}/handle', HandleWhatsappInboundController::class)
    ->name('whatsapp.inbound.handle');
