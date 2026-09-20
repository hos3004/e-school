<?php

declare(strict_types=1);

namespace Modules\Students\Domain\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Shared\Domain\DomainEvent;

/**
 * قدّم متقدّم طلب التحاق بالمؤسسة.
 *
 * التقديم **ليس قبولًا**: لا يوجد بعدُ ملف طالب ولا قيد ولا عضوية مجموعة
 * (docs/client-answers.md §أ). الحدث إخطاري ليصل الطلب إلى مراجعة الإدارة.
 *
 * الكورس المطلوب وتاريخ التقديم جزء من الحدث لأن الإشعار يعرضهما: بدونهما
 * تصل رسالة واحدة لا تفرّق بين طلبين لنفس الطالب. اسم الكورس خريطة لغات،
 * وتاريخ التقديم UTC يحوّله المحرّك إلى توقيت المستلم ولغته.
 */
final class RegistrationSubmitted extends DomainEvent implements ShouldDispatchAfterCommit
{
    /**
     * @param array<string, string> $courseName
     */
    public function __construct(
        public readonly string $applicationId,
        public readonly string $organizationId,
        public readonly string $fullName,
        public readonly ?string $studentUserId,
        public readonly array $courseName,
        public readonly string $submittedAt,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'registration.submitted';
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
            'full_name' => $this->fullName,
            'student_user_id' => $this->studentUserId,
            'course_name' => $this->courseName,
            'submitted_at' => $this->submittedAt,
        ];
    }
}
