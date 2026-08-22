<?php

declare(strict_types=1);

namespace Modules\Students\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * قُبل طلب الالتحاق وأُنشئ ملف الطالب.
 *
 * القبول يجعل الطالب جاهزًا للتوزيع فقط — لا يوزّعه على برنامج أو مجموعة
 * (docs/client-answers.md §أ).
 */
final class RegistrationAccepted extends DomainEvent
{
    public function __construct(
        public readonly string $applicationId,
        public readonly string $organizationId,
        public readonly string $studentProfileId,
        public readonly string $studentUserId,
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
        ];
    }
}
