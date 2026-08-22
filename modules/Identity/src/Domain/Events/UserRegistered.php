<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * سُجّل حساب مستخدم جديد في المؤسسة.
 */
final class UserRegistered extends DomainEvent
{
    public function __construct(
        public readonly string $userId,
        public readonly string $organizationId,
        public readonly string $email,
        public readonly string $locale,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'identity.user_registered';
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
            'email' => $this->email,
            'locale' => $this->locale,
        ];
    }
}
