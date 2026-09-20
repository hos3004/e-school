<?php

declare(strict_types=1);

namespace Modules\Students\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * رُفض طلب الالتحاق بسبب مكتوب.
 *
 * الرفض بلا سبب مرفوض على مستوى الـFormRequest — والسبب جزء من الحدث
 * لأن الإشعار يعرضه للمتقدّم، ومعه اسم الطالب والكورس حتى يعرف صاحب
 * الرسالة أيّ طلب رُفض. اسم الكورس خريطة لغات يختار منها المحرّك لغة المستلم.
 */
final class RegistrationRejected extends DomainEvent
{
    /**
     * @param array<string, string> $courseName
     */
    public function __construct(
        public readonly string $applicationId,
        public readonly string $organizationId,
        public readonly string $reason,
        public readonly ?string $studentUserId,
        public readonly string $studentName,
        public readonly array $courseName,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'registration.rejected';
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
            'reason' => $this->reason,
            'student_user_id' => $this->studentUserId,
            'student_name' => $this->studentName,
            'course_name' => $this->courseName,
        ];
    }
}
