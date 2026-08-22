<?php

declare(strict_types=1);

namespace Modules\Audit\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Audit\Application\Queries\AuditEntryData;
use Modules\Audit\Domain\Models\AuditLog;

/**
 * تمثيل قيدة التدقيق في الـ API — قراءة فقط، بلا مسارات تعديل.
 *
 * يقبل النموذج أو الـ DTO القادم من خدمة الاستعلام.
 *
 * @mixin AuditLog|AuditEntryData
 */
final class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof AuditEntryData) {
            return $this->fromDto($this->resource);
        }

        /** @var AuditLog $entry */
        $entry = $this->resource;

        return [
            'id' => (string) $entry->getKey(),
            'organization_id' => $entry->organization_id,
            'actor_id' => $entry->actor_id,
            'actor_type' => $entry->actor_type?->value,
            'acting_for_user_id' => $entry->acting_for_user_id,
            'action' => $entry->action,
            'auditable_type' => $entry->auditable_type,
            'auditable_id' => $entry->auditable_id,
            'old_values' => $entry->old_values,
            'new_values' => $entry->new_values,
            'reason' => $entry->reason,
            'ip_address' => $entry->ip_address,
            'correlation_id' => $entry->correlation_id,
            'created_at' => $entry->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromDto(AuditEntryData $dto): array
    {
        return [
            'id' => $dto->id,
            'organization_id' => $dto->organizationId,
            'actor_id' => $dto->actorId,
            'actor_type' => $dto->actorType->value,
            'acting_for_user_id' => $dto->actingForUserId,
            'action' => $dto->action,
            'auditable_type' => $dto->auditableType,
            'auditable_id' => $dto->auditableId,
            'old_values' => $dto->oldValues,
            'new_values' => $dto->newValues,
            'reason' => $dto->reason,
            'ip_address' => $dto->ipAddress,
            'correlation_id' => $dto->correlationId,
            'created_at' => $dto->createdAt,
        ];
    }
}
