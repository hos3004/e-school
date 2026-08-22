<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * حُدِّث الملف الشخصي للمستخدم.
 */
final class UserProfileUpdated extends DomainEvent
{
    /**
     * @param  array<string, mixed>  $changed  الحقول المتغيرة: القيمة القديمة ← الجديدة
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $organizationId,
        public readonly array $changed,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'identity.user_profile_updated';
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
            'changed_fields' => array_keys($this->changed),
            'changed' => $this->changed,
        ];
    }
}
