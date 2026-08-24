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
 */
final class RegistrationSubmitted extends DomainEvent implements ShouldDispatchAfterCommit
{
    public function __construct(
        public readonly string $applicationId,
        public readonly string $organizationId,
        public readonly string $fullName,
        public readonly ?string $studentUserId,
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
        ];
    }
}
