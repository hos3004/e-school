<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * تغيّرت حالة الحساب (تفعيل / إيقاف / تجميد).
 *
 * يستهلكه Audit للتدقيق، وAccessControl لإبطال الجلسات عند الإيقاف.
 */
final class UserStatusChanged extends DomainEvent
{
    public function __construct(
        public readonly string $userId,
        public readonly string $organizationId,
        public readonly string $from,
        public readonly string $to,
        public readonly string $reason,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'identity.user_status_changed';
    }

    public function module(): string
    {
        return 'Identity';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'user_id' => $this->userId,
            'organization_id' => $this->organizationId,
            'from' => $this->from,
            'to' => $this->to,
            'reason' => $this->reason,
        ];
    }
}
