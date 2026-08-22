<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * سُحب الجهاز — لن يصل منه إشعارات بعد الآن.
 */
final class DeviceRevoked extends DomainEvent
{
    public function __construct(
        public readonly string $deviceId,
        public readonly string $userId,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'identity.device_revoked';
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
            'device_id' => $this->deviceId,
            'user_id' => $this->userId,
        ];
    }
}
