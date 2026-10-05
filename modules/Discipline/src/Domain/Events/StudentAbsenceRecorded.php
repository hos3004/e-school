<?php

declare(strict_types=1);

namespace Modules\Discipline\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * غياب طالب صار نهائيًا — يُخطَر به الطالب ووليّه في كل مرة.
 *
 * هذا الحدث موجّه للإخطار وحده، ولا يحل محل ViolationRecorded الذي يخص
 * محرّك الانضباط والتقارير. سبب فصلهما: حدث الانضباط يحمل معرّفات ملفات
 * (student_profile_id) ومحرّك الإشعارات لا يعرف جداول غيره، فلا بد من حدث
 * يحمل معرّفات مستخدمين نهائية وسياق الرسالة جاهزًا.
 *
 * العتبة التي يُذكرها النص تأتي من config('discipline.ladder') ولا تُكتب رقمًا
 * في قالب ولا في كود: تغيير سياسة المدرسة يجب أن يغيّر الرسالة معها.
 */
final class StudentAbsenceRecorded extends DomainEvent
{
    /**
     * @param list<string> $guardianUserIds
     * @param array<string, string> $courseName
     */
    public function __construct(
        public readonly string $violationId,
        public readonly string $organizationId,
        public readonly string $studentUserId,
        public readonly array $guardianUserIds,
        public readonly string $studentName,
        public readonly array $courseName,
        public readonly ?string $scheduledStart,
        public readonly string $violationType,
        public readonly int $absenceCount,
        public readonly int $freezeThreshold,
        public readonly int $remainingBeforeFreeze,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'discipline.absence_recorded';
    }

    public function module(): string
    {
        return 'Discipline';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'violation_id' => $this->violationId,
            'organization_id' => $this->organizationId,
            'student_user_id' => $this->studentUserId,
            'guardian_user_ids' => $this->guardianUserIds,
            'student_name' => $this->studentName,
            'course_name' => $this->courseName,
            'scheduled_start' => $this->scheduledStart,
            'violation_type' => $this->violationType,
            'absence_count' => $this->absenceCount,
            'freeze_threshold' => $this->freezeThreshold,
            'remaining_before_freeze' => $this->remainingBeforeFreeze,
        ];
    }
}
