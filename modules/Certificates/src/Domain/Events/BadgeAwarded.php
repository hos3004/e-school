<?php

declare(strict_types=1);

namespace Modules\Certificates\Domain\Events;

/**
 * مُنحت شارة لمستخدم — حدث يستهلكه الإشعارات والملف الشخصي.
 */
final class BadgeAwarded extends CertificateEvent
{
    public function __construct(
        public readonly string $awardId,
        public readonly string $organizationId,
        public readonly string $badgeId,
        public readonly string $badgeCode,
        public readonly string $userId,
        public readonly ?string $awardedById,
        public readonly ?string $reason,
        public readonly string $awardedAt,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'certificates.badge_awarded';
    }

    public function payload(): array
    {
        return [
            'award_id' => $this->awardId,
            'organization_id' => $this->organizationId,
            'badge_id' => $this->badgeId,
            'badge_code' => $this->badgeCode,
            'user_id' => $this->userId,
            'awarded_by_id' => $this->awardedById,
            'reason' => $this->reason,
            'awarded_at' => $this->awardedAt,
        ];
    }
}
