<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Presentation\Http\Controllers\CancelNotificationController;
use Modules\Notifications\Presentation\Http\Controllers\DownloadPopupAttachmentController;
use Modules\Notifications\Presentation\Http\Controllers\ListDeliveryAttemptsController;
use Modules\Notifications\Presentation\Http\Controllers\ListNotificationsController;
use Modules\Notifications\Presentation\Http\Controllers\ListPreferencesController;
use Modules\Notifications\Presentation\Http\Controllers\MarkAllNotificationsAsReadController;
use Modules\Notifications\Presentation\Http\Controllers\MarkNotificationAsReadController;
use Modules\Notifications\Presentation\Http\Controllers\QueueNotificationController;
use Modules\Notifications\Presentation\Http\Controllers\RetryNotificationController;
use Modules\Notifications\Presentation\Http\Controllers\ShowNotificationController;
use Modules\Notifications\Presentation\Http\Controllers\ShowPopupAttachmentController;
use Modules\Notifications\Presentation\Http\Controllers\StorePopupCampaignMediaController;
use Modules\Notifications\Presentation\Http\Controllers\UnreadNotificationCountController;
use Modules\Notifications\Presentation\Http\Controllers\UpdatePreferenceController;

/*
|--------------------------------------------------------------------------
| مسارات موديول Notifications — الـ API
|--------------------------------------------------------------------------
|
| يُحمَّل هذا الملف تلقائيًا من ModuleRegistry::loadRoutes() ضمن مجموعة
| middleware «api» وبالبادئة api/.
*/

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('notifications', ListNotificationsController::class)->name('notifications.index');
    Route::get('notifications/unread-count', UnreadNotificationCountController::class)
        ->name('notifications.unread-count');
    Route::post('notifications/mark-all-as-read', MarkAllNotificationsAsReadController::class)
        ->name('notifications.mark-all-as-read');
    Route::get('notifications/{outbox}', ShowNotificationController::class)->name('notifications.show');
    Route::post('notifications/{outbox}/mark-as-read', MarkNotificationAsReadController::class)
        ->name('notifications.mark-as-read');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('notifications', QueueNotificationController::class)
        ->middleware('can:create,'.NotificationOutbox::class)
        ->name('notifications.store');

    Route::prefix('notifications/{outbox}')->group(function (): void {
        Route::post('cancel', CancelNotificationController::class)->name('notifications.cancel');
        Route::post('retry', RetryNotificationController::class)->name('notifications.retry');
        Route::get('attempts', ListDeliveryAttemptsController::class)->name('notifications.attempts');
    });

    Route::get('notification-preferences', ListPreferencesController::class)->name('notification-preferences.index');
    Route::put('notification-preferences', UpdatePreferenceController::class)->name('notification-preferences.update');
});

/*
| ميديا الحملات المنبثقة — الرفع والعرض المضمَّن يتطلبان مصادقة (جلسة أو
| Sanctum عبر stateful domains). العرض يفرض بادئة المسار من config ولا
| يثق بالقيم المخزَّنة — انظر تعليقات المتحكم نفسه لتفاصيل الحماية من IDOR.
|
| طلب رابط التنزيل الموقَّع (download-request) ليس هنا عمدًا: يحتاج حلّ
| جمهور المستخدم عبر Modules\Identity\Domain\Contracts\UserQueryService،
| وهذا الموديول ممنوع من الاعتماد على Identity (انظر tests/Architecture).
| هو مسجَّل بدلًا من ذلك في routes/api.php الجذري عبر
| App\Http\Controllers\Api\PopupMessageController — تمامًا كما تعيش
| popups/active وpopups/{campaign}/{interaction} هناك لا هنا.
*/
Route::middleware('auth:sanctum')->prefix('popups/{campaign}')->whereUlid('campaign')->group(function (): void {
    Route::post('media', StorePopupCampaignMediaController::class)
        ->name('popups.media.store');

    Route::get('media/{media}', ShowPopupAttachmentController::class)
        ->whereUlid('media')
        ->name('popups.media.show');
});

/*
| رابط التنزيل الموقَّع — بلا Sanctum عمدًا: مدير تنزيل نظام الموبايل لا
| يرسل ترويسة Authorization. الحماية بالكامل عبر توقيع الرابط (يغطي
| campaign وmedia وuser معًا) ومدته القصيرة من config.
*/
Route::middleware('signed')
    ->get('popups/{campaign}/media/{media}/download/{user}', DownloadPopupAttachmentController::class)
    ->whereUlid(['campaign', 'media', 'user'])
    ->name('popups.media.download');
