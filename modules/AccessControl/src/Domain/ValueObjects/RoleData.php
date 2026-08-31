<?php

declare(strict_types=1);

namespace Modules\AccessControl\Domain\ValueObjects;

/** DTO عام للقراءة فقط؛ لا يسرّب نموذج Eloquent خارج الموديول. */
final readonly class RoleData
{
    public function __construct(
        public string $id,
        public ?string $organizationId,
        public string $name,
        public string $guardName,
        public bool $isSystem,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'name' => $this->name,
            'guard_name' => $this->guardName,
            'is_system' => $this->isSystem,
        ];
    }
}
