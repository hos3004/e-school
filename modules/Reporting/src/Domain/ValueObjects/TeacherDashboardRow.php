<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\ValueObjects;

/**
 * صف لوحة المعلم للقراءة — DTO مسطّح لا يحمل Eloquent.
 */
final readonly class TeacherDashboardRow
{
    public function __construct(
        public string $staffProfileId,
        public int $sessionsCompleted,
        public int $cancellationsBySelf,
        public int $postponements,
        public int $payoutMinor,
        public string $currency,
        public ?string $lastSessionAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'staff_profile_id' => $this->staffProfileId,
            'sessions_completed' => $this->sessionsCompleted,
            'cancellations_by_self' => $this->cancellationsBySelf,
            'postponements' => $this->postponements,
            'payout_minor' => $this->payoutMinor,
            'currency' => $this->currency,
            'last_session_at' => $this->lastSessionAt,
        ];
    }
}
