<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * حُدّث إعداد على مستوى المؤسسة.
 */
final class OrganizationSettingUpdated extends DomainEvent
{
    /**
     * @param mixed $value قيمة بدائية (سلسلة/عدد/منطقية) أو بنية مصفوفية بسيطة
     */
    public function __construct(
        public readonly string $organizationId,
        public readonly string $key,
        public readonly mixed $value,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'organization.setting_updated';
    }

    public function module(): string
    {
        return 'organization';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'key' => $this->key,
            'value' => $this->value,
        ];
    }
}
