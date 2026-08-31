<?php

declare(strict_types=1);

use App\Services\VirtualClassroom\RecordingSynchronizer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Modules\Recordings\Application\Actions\ExpireRecordingsAction;
use Symfony\Component\Console\Command\Command;

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
| المهام المجدولة على مستوى المنصة. مهام الموديولات تُسجَّل داخل
| ModuleServiceProvider الخاص بكل موديول.
|
| ملاحظة تسليمية: أوامر sessions:dispatch-reminders و
| sessions:finalize-due ليست موجودة
| بعد — تُضاف مع أوامر موديولاتها (المهام 03) ويُعاد جدولتها هنا
| عند إنشائها. جدولة أمر غير موجود تفشل كل دورة وتلوث السجل.
*/

// إعادة محاولة الإشعارات الفاشلة
Schedule::command('notifications:retry-failed')->everyFifteenMinutes()->withoutOverlapping();

// توزيع الإشعارات التي حان موعدها إلى عمال قناة الإرسال
Schedule::command('notifications:dispatch-due')->everyMinute()->withoutOverlapping();

Artisan::command('classroom:sync-recordings', function (RecordingSynchronizer $synchronizer): int {
    $this->info(__('virtualclassroom::messages.recordings_synced', [
        'count' => $synchronizer->syncKnownClassrooms(),
    ]));

    return Command::SUCCESS;
})->purpose('Synchronize ready BigBlueButton recordings');

Schedule::command('classroom:sync-recordings')->everyTenMinutes()->withoutOverlapping();
Schedule::command('classroom:provision-upcoming')->everyMinute()->withoutOverlapping();

Artisan::command('recordings:enforce-retention', function (ExpireRecordingsAction $action): int {
    $processed = $action->execute(limit: max(1, (int) config('recordings.retention_batch_size', 100)));
    $this->info(__('recordings::messages.retention_summary', ['count' => count($processed)]));

    return Command::SUCCESS;
})->purpose('Archive or expire recordings that passed their retention period');

Schedule::command('recordings:enforce-retention')->hourly()->withoutOverlapping();
