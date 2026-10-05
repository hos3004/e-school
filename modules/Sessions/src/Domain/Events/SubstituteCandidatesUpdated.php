<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Events;

final class SubstituteCandidatesUpdated extends SessionEvent
{
    /** @param list<string> $candidateStaffProfileIds */
    public function __construct(
        string $sessionId,
        string $organizationId,
        string $courseId,
        string $staffProfileId,
        public readonly string $apologyId,
        public readonly array $candidateStaffProfileIds,
        public readonly string $searchedAt,
        ?string $correlationId = null,
    ) {
        parent::__construct(
            $sessionId,
            $organizationId,
            $courseId,
            $staffProfileId,
            null,
            $correlationId,
        );
    }

    public function name(): string
    {
        return 'sessions.substitute_candidates_updated';
    }

    public function payload(): array
    {
        return [
            'session_id' => $this->sessionId,
            'organization_id' => $this->organizationId,
            'course_id' => $this->courseId,
            'staff_profile_id' => $this->staffProfileId,
            'apology_id' => $this->apologyId,
            'candidate_staff_profile_ids' => $this->candidateStaffProfileIds,
            'candidate_count' => count($this->candidateStaffProfileIds),
            'searched_at' => $this->searchedAt,
        ];
    }
}
