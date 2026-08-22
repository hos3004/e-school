<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * أُنشئت مؤسسة جديدة.
 */
final class OrganizationCreated extends DomainEvent
{
    public function __construct(
        public readonly string $organizationId,
        public readonly string $slug,
        public readonly string $defaultLocale,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'organization.created';
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
            'slug' => $this->slug,
            'default_locale' => $this->defaultLocale,
        ];
    }
}
