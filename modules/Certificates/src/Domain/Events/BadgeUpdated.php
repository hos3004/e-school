<?php

declare(strict_types=1);

namespace Modules\Certificates\Domain\Events;

/**
 * عُدّلت شارة قائمة — الاسم أو الوصف أو التفعيل.
 */
final class BadgeUpdated extends CertificateEvent
{
    /**
     * @param array<string, mixed> $changes الحقول المتغيرة: قيمة قبلية => بعدية
     */
    public function __construct(
        public readonly string $badgeId,
        public readonly string $organizationId,
        public readonly string $code,
        public readonly string $tier,
        public readonly bool $isActive,
        public readonly array $changes,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'certificates.badge_updated';
    }

    public function payload(): array
    {
        return [
            'badge_id' => $this->badgeId,
            'organization_id' => $this->organizationId,
            'code' => $this->code,
            'tier' => $this->tier,
            'is_active' => $this->isActive,
            'changes' => $this->changes,
        ];
    }
}
