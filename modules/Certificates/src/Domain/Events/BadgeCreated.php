<?php

declare(strict_types=1);

namespace Modules\Certificates\Domain\Events;

/**
 * أُنشئت شارة جديدة في كتالوج المؤسسة.
 */
final class BadgeCreated extends CertificateEvent
{
    public function __construct(
        public readonly string $badgeId,
        public readonly string $organizationId,
        public readonly string $code,
        public readonly string $tier,
        public readonly bool $isActive,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'certificates.badge_created';
    }

    public function payload(): array
    {
        return [
            'badge_id' => $this->badgeId,
            'organization_id' => $this->organizationId,
            'code' => $this->code,
            'tier' => $this->tier,
            'is_active' => $this->isActive,
        ];
    }
}
