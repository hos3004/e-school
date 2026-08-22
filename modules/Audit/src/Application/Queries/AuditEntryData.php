<?php

declare(strict_types=1);

namespace Modules\Audit\Application\Queries;

use Modules\Audit\Domain\Enums\AuditActorType;

/**
 * DTO للقراءة فقط — يُستخدم لتمرير القيود خارج حدود الموديول
 * بدل Eloquent model، حفاظًا على حدود المعمارية.
 */
final readonly class AuditEntryData
{
    /**
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    public function __construct(
        public string $id,
        public ?string $organizationId,
        public ?string $actorId,
        public AuditActorType $actorType,
        public ?string $actingForUserId,
        public string $action,
        public string $auditableType,
        public ?string $auditableId,
        public ?array $oldValues,
        public ?array $newValues,
        public ?string $reason,
        public ?string $ipAddress,
        public ?string $correlationId,
        public string $createdAt,
    ) {}
}
