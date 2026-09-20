<?php

declare(strict_types=1);

use App\Http\Controllers\Console\DirectoryController;
use App\Http\Controllers\Console\MessagingController;
use App\Http\Controllers\Console\NotificationsPageController;
use App\Http\Controllers\Console\NotificationTemplateController;
use App\Http\Controllers\Console\QuranController;
use App\Http\Controllers\Console\ReportsController;
use App\Http\Controllers\Console\SessionPayController;
use App\Http\Controllers\Console\SettingsController;
use App\Http\Controllers\Console\SettingsOperationsController;
use App\Http\Controllers\Console\TeacherFinancialVisibilityController;
use App\Http\Controllers\Console\WhatsappCampaignController;
use App\Http\Controllers\Console\WhatsappController;
use App\Http\Controllers\Console\WorkspaceController;
use Illuminate\Support\Facades\Route;
use Modules\Integrations\Presentation\Http\Controllers\RegisterGreenApiWebhookController;
use Modules\Integrations\Presentation\Http\Controllers\SaveGreenApiSettingsController;
use Modules\Reporting\Presentation\Http\Controllers\ExportOperationalReportPdfController;

Route::get('/', WorkspaceController::class)->name('home');
Route::get('/notifications', NotificationsPageController::class)->name('notifications');

/*
 * قوالب الرسائل. القالب العام مشترك بين المؤسسات فلا يُعدَّل ولا يُحذف من
 * هنا — السياسة هي من تفرض ذلك، والواجهة تخفي أزراره فقط.
 */
Route::middleware('can:settings.manage')->prefix('notification-templates')->name('notification-templates.')->group(function (): void {
    Route::post('/', [NotificationTemplateController::class, 'store'])->name('store');
    Route::put('{template}', [NotificationTemplateController::class, 'update'])->whereUlid('template')->name('update');
    Route::delete('{template}', [NotificationTemplateController::class, 'destroy'])->whereUlid('template')->name('destroy');
});
Route::get('/directory', DirectoryController::class)->name('directory');

/*
 * مركز واتساب. قسم جامع لحالة القناة ومفتاح إيقافها وقوالبها وسجلّها ومحاكاة
 * الإرسال — ولا يلغي مداخل واتساب الأخرى في المنصة، فكلها تمر بنفس المحرّك.
 */
Route::prefix('whatsapp')->name('whatsapp.')->group(function (): void {
    Route::get('/', WhatsappController::class)->name('index');
    Route::post('toggle', [WhatsappController::class, 'toggle'])
        ->middleware('can:integrations.connection.update')->name('toggle');
    Route::post('preview', [WhatsappController::class, 'preview'])
        ->middleware('can:notifications.outbox.create')->name('preview');

    /*
     * الحملات: إرسال جماعي إلى قائمة أرقام لا تملك حسابات على المنصة. مفتاح
     * إيقاف القناة أعلاه يسري عليها أيضًا — المهمة تفحصه قبل كل رسالة.
     */
    Route::middleware('can:notifications.outbox.create')->prefix('campaigns')->name('campaigns.')->group(function (): void {
        Route::post('preview', [WhatsappCampaignController::class, 'preview'])->name('preview');
        Route::post('/', [WhatsappCampaignController::class, 'store'])->name('store');
        Route::get('{campaign}', [WhatsappCampaignController::class, 'show'])->whereUlid('campaign')->name('show');
        Route::post('{campaign}/start', [WhatsappCampaignController::class, 'start'])->whereUlid('campaign')->name('start');
        Route::post('{campaign}/stop', [WhatsappCampaignController::class, 'stop'])->whereUlid('campaign')->name('stop');
    });
});

/*
 * المراسلة اليدوية: من الملف الشخصي لشخص واحد، ومن صفحة الفصل لأطرافه.
 * كلاهما يمر بمحرّك الإشعارات وصندوق الصادر، فيبقى التتبع والتدقيق وإعادة
 * المحاولة والجدولة واحدة مهما اختلف مصدر الإرسال.
 */
Route::middleware('can:notifications.outbox.create')->prefix('messages')->name('messages.')->group(function (): void {
    Route::get('targets', [MessagingController::class, 'targets'])->name('targets');
    Route::get('templates', [MessagingController::class, 'templates'])->name('templates');
    Route::post('audience', [MessagingController::class, 'audience'])->name('audience');
    Route::post('{kind}/{profile}', [MessagingController::class, 'person'])
        ->whereIn('kind', ['students', 'teachers', 'guardians'])->whereUlid('profile')->name('person');
});
Route::get('/reports', ReportsController::class)->middleware('can:report.view')->name('reports');
Route::get('/reports/pdf', ExportOperationalReportPdfController::class)->middleware(['can:report.view', 'can:report.export'])->name('reports.pdf');

Route::get('/settings', [SettingsController::class, 'index'])->middleware('can:organizations.view')->name('settings');
Route::put('/settings', [SettingsController::class, 'update'])->middleware('can:organizations.update')->name('settings.update');

Route::post('/settings/green-api/webhook', RegisterGreenApiWebhookController::class)
    ->middleware(['can:settings.manage', 'can:integrations.connection.update'])->name('settings.green-api.webhook');

Route::post('/settings/green-api', SaveGreenApiSettingsController::class)
    ->middleware(['can:settings.manage', 'can:integrations.connection.update'])->name('settings.green-api');

Route::post('/settings/session-pay', [SessionPayController::class, 'store'])->middleware('can:organizations.manage_settings')->name('settings.session-pay');

Route::post('/settings/{operation}', SettingsOperationsController::class)
    ->whereIn('operation', ['accounts', 'notifications', 'calendar-create', 'calendar-activate', 'calendar-close', 'holiday-create', 'holiday-remove'])
    ->middleware('can:organizations.view')->name('settings.operation');

Route::get('/quran', [QuranController::class, 'index'])->middleware('can:student.view.any')->name('quran');
Route::get('/quran/availability', [QuranController::class, 'availability'])->middleware(['can:student.view.any', 'can:schedule.manage'])->name('quran.availability');
Route::get('/quran/rate', [QuranController::class, 'rate'])->middleware(['can:student.view.any', 'can:schedule.manage'])->name('quran.rate');
Route::post('/quran/{student}', [QuranController::class, 'store'])->whereUlid('student')->middleware(['can:student.view.any', 'can:schedule.manage'])->name('quran.store');

require __DIR__.'/console-quran.php';

require __DIR__.'/console-sessions.php';

Route::put('/teachers/{profile}/financial-visibility', TeacherFinancialVisibilityController::class)->whereUlid('profile')->middleware('can:staff.contract.update')->name('teachers.financial-visibility');
