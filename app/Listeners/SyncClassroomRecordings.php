<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\VirtualClassroom\RecordingSynchronizer;
use Illuminate\Support\Facades\Log;
use Modules\VirtualClassroom\Domain\Events\ClassroomEnded;
use Throwable;

final readonly class SyncClassroomRecordings
{
    public function __construct(
        private RecordingSynchronizer $synchronizer,
    ) {}

    public function handle(ClassroomEnded $event): void
    {
        try {
            $this->synchronizer->syncClassroom($event->classroomId);
        } catch (Throwable $exception) {
            /*
             * BBB قد لا ينهي معالجة التسجيل لحظة انتهاء الحصة. ندوّن الفشل
             * ونترك الأمر المجدول يعيد المحاولة، ولا نعيد webhook إلى BBB.
             */
            Log::warning('virtualclassroom.recording_sync_deferred', [
                'classroom_id' => $event->classroomId,
                'exception' => $exception::class,
            ]);
        }
    }
}
