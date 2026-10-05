<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Events;

/**
 * أُنشئت حصة وجُدولت وأُعلنت للطرفين.
 */
final class SessionScheduled extends SessionEvent
{
    public function __construct(
        string $sessionId,
        string $organizationId,
        string $courseId,
        string $staffProfileId,
        public readonly string $scheduledStart,
        public readonly string $scheduledEnd,
        public readonly ?string $groupId,
        /**
         * هل وُلّدت هذه الحصة ضمن تفريغ جدول متكرر؟
         *
         * تعديل جدول واحد يولّد عشرات الحصص دفعة واحدة؛ إشعار لكل واحدة منها
         * يغرق المعلم والطالب. الإشعار المناسب لهذه الحالة ملخّص الجدول مرة
         * واحدة، والحدث يبقى قائمًا لبقية المستمعين.
         */
        public readonly bool $generatedFromSchedule = false,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($sessionId, $organizationId, $courseId, $staffProfileId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'sessions.scheduled';
    }

    public function payload(): array
    {
        return [
            'session_id' => $this->sessionId,
            'organization_id' => $this->organizationId,
            'course_id' => $this->courseId,
            'staff_profile_id' => $this->staffProfileId,
            'group_id' => $this->groupId,
            'scheduled_start' => $this->scheduledStart,
            'scheduled_end' => $this->scheduledEnd,
            'generated_from_schedule' => $this->generatedFromSchedule,
        ];
    }
}
