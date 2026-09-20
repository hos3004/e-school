<?php

declare(strict_types=1);

namespace Modules\Students\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * قُبل طلب الالتحاق وأُنشئ ملف الطالب.
 *
 * القبول يجعل الطالب جاهزًا للتوزيع فقط — لا يوزّعه على برنامج أو مجموعة
 * (docs/client-answers.md §أ).
 *
 * اسم الطالب والكورس وكود الطالب جزء من الحدث لأن الإشعار يعرضها: الطالب
 * يقدّم أكثر من طلب لكورسات مختلفة، فرسالة بلا اسم الكورس تصل مكرّرة بلا
 * فرق بينها. اسم الكورس خريطة لغات ليختار المحرّك لغة كل مستلم.
 */
final class RegistrationAccepted extends DomainEvent
{
    /**
     * @param array<string, string> $courseName
     */
    public function __construct(
        public readonly string $applicationId,
        public readonly string $organizationId,
        public readonly string $studentProfileId,
        public readonly string $studentUserId,
        public readonly string $studentName,
        public readonly array $courseName,
        public readonly string $studentCode,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'registration.approved';
    }

    public function module(): string
    {
        return 'Students';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'application_id' => $this->applicationId,
            'organization_id' => $this->organizationId,
            'student_profile_id' => $this->studentProfileId,
            'student_user_id' => $this->studentUserId,
            'student_name' => $this->studentName,
            'course_name' => $this->courseName,
            'student_code' => $this->studentCode,
        ];
    }
}
