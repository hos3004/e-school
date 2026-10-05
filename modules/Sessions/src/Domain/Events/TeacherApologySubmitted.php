<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Events;

/**
 * قدّم معلم اعتذارًا عن حصة؛ يتبعه الاعتماد التلقائي في مسار التطبيق نفسه.
 *
 * الحدث إخطاري بحت: **لم يتغيّر شيء في الحصة بعد**. المعلم ما زال مسندًا،
 * والحصة قائمة بموعدها وحالتها.
 *
 * `teacherUserId` مضمَّن عمدًا: موديول Notifications لا يستطيع تحويل
 * staff_profile_id إلى مستقبِل بنفسه دون كسر حدود الموديولات، فنمرّر
 * معرّف المستخدم من هنا حيث نملكه أصلًا.
 */
final class TeacherApologySubmitted extends SessionEvent
{
    public function __construct(
        string $sessionId,
        string $organizationId,
        string $courseId,
        string $staffProfileId,
        public readonly string $apologyId,
        public readonly ?string $teacherUserId,
        public readonly bool $isLateNotice,
        public readonly int $noticeMinutes,
        public readonly string $scheduledStart,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($sessionId, $organizationId, $courseId, $staffProfileId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'sessions.teacher_apology.submitted';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'session_id' => $this->sessionId,
            'organization_id' => $this->organizationId,
            'course_id' => $this->courseId,
            'staff_profile_id' => $this->staffProfileId,
            'apology_id' => $this->apologyId,
            'teacher_user_id' => $this->teacherUserId,
            'is_late_notice' => $this->isLateNotice,
            'notice_minutes' => $this->noticeMinutes,
            'scheduled_start' => $this->scheduledStart,
        ];
    }
}
