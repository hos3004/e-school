<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Sessions\Application\Actions\StartSessionAction;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\VirtualClassroom\Domain\Enums\JoinRole;
use Modules\VirtualClassroom\Domain\Events\ClassroomParticipantJoined;
use Throwable;

/**
 * دخول المعلم إلى الغرفة يبدأ الحصة (in_progress).
 *
 * لا مسار في بوابة المعلم ينقل الحصة من scheduled، فكان زر التقرير وقائمة
 * كشوف الحضور المعلّقة لا يظهران. يعمل داخل معاملة الـwebhook، لذا لا يرمي:
 * فشل البدء لا يجوز أن يُسقط تسجيل حدث الدخول نفسه.
 */
final readonly class StartSessionOnTeacherJoin
{
    public function __construct(
        private StartSessionAction $start,
    ) {}

    public function handle(ClassroomParticipantJoined $event): void
    {
        if ($event->role !== JoinRole::Moderator || $event->userId === null || $event->userId === '') {
            return;
        }

        $session = Session::query()->find($event->sessionId);

        if (!$session instanceof Session
            || !in_array($session->status, [SessionStatus::Scheduled, SessionStatus::Confirmed], true)) {
            return;
        }

        try {
            $this->start->execute(
                $session,
                $event->userId,
                (string) __('sessions::messages.started_by_teacher_join'),
            );
        } catch (Throwable $exception) {
            Log::warning('sessions.auto_start_failed', [
                'session_id' => $event->sessionId,
                'exception' => $exception::class,
            ]);
        }
    }
}
