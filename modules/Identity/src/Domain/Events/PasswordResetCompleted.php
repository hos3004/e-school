<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * اكتملت إعادة تعيين كلمة المرور بنجاح.
 *
 * يستهلكه AccessControl لإبطال الجلسات والأجهزة القديمة.
 */
final class PasswordResetCompleted extends DomainEvent
{
    public function __construct(
        public readonly string $userId,
        public readonly string $organizationId,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'identity.password_reset_completed';
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
        ];
    }
}
