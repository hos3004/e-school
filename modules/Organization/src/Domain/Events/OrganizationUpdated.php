<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * عُدّلت بيانات المؤسسة الأساسية.
 */
final class OrganizationUpdated extends DomainEvent
{
    /**
     * @param  list<string>  $changedFields
     */
    public function __construct(
        public readonly string $organizationId,
        public readonly array $changedFields,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'organization.updated';
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
            'changed_fields' => $this->changedFields,
        ];
    }
}
