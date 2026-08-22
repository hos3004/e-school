<?php

declare(strict_types=1);

namespace Modules\Audit\Domain\Events;

use Modules\Audit\Domain\Enums\AuditActorType;
use Shared\Domain\DomainEvent;

/**
 * أُنشئت قيدة تدقيق جديدة.
 *
 * الحدث للقراءة فقط: باقي الموديولات قد تستمع إليه لعرض آخر النشاطات،
 * لكن موديول Audit هو وحده من يكتب في audit_log.
 *
 * الحمولة معرّفات وقيَم بدائية فقط — لا Eloquent models.
 */
final class AuditEntryRecorded extends DomainEvent
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function __construct(
        public readonly string $auditLogId,
        public readonly ?string $organizationId,
        ?string $actorId,
        public readonly AuditActorType $actorType,
        public readonly string $action,
        public readonly string $auditableType,
        public readonly ?string $auditableId,
        public readonly ?array $oldValues,
        public readonly ?array $newValues,
        public readonly ?string $actingForUserId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'audit.entry_recorded';
    }

    public function module(): string
    {
        return 'audit';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'audit_log_id' => $this->auditLogId,
            'organization_id' => $this->organizationId,
            'actor_id' => $this->actorId,
            'actor_type' => $this->actorType->value,
            'action' => $this->action,
            'auditable_type' => $this->auditableType,
            'auditable_id' => $this->auditableId,
            'old_values' => $this->oldValues,
            'new_values' => $this->newValues,
            'acting_for_user_id' => $this->actingForUserId,
        ];
    }
}
