<?php

declare(strict_types=1);

namespace Modules\Audit\Infrastructure\Persistence;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Audit\Application\Queries\AuditEntryData;
use Modules\Audit\Domain\Contracts\AuditQueryService;
use Modules\Audit\Domain\Models\AuditLog;

/**
 * تنفيذ قراءة القيود — يُربط بالعقد عبر AuditServiceProvider::bindings().
 */
final class AuditLogQueryService implements AuditQueryService
{
    public function paginateForOrganization(
        ?string $organizationId,
        array $filters = [],
        int $perPage = 50,
        int $page = 1,
    ): LengthAwarePaginator {
        return AuditLog::query()
            ->forOrganization($organizationId)
            ->when(
                isset($filters['action']) && $filters['action'] !== '',
                fn ($q) => $q->forAction((string) $filters['action']),
            )
            ->when(
                isset($filters['auditable_type']) && $filters['auditable_type'] !== '',
                fn ($q) => $q->where('auditable_type', $filters['auditable_type']),
            )
            ->when(
                isset($filters['auditable_id']) && $filters['auditable_id'] !== '',
                fn ($q) => $q->where('auditable_id', $filters['auditable_id']),
            )
            ->when(
                isset($filters['actor_id']) && $filters['actor_id'] !== '',
                fn ($q) => $q->where('actor_id', $filters['actor_id']),
            )
            ->when(
                isset($filters['correlation_id']) && $filters['correlation_id'] !== '',
                fn ($q) => $q->where('correlation_id', $filters['correlation_id']),
            )
            ->orderByDesc('created_at')
            ->paginate(perPage: max(1, min($perPage, 200)), page: max(1, $page))
            ->through(fn (AuditLog $entry): AuditEntryData => self::toDto($entry));
    }

    public static function toDto(AuditLog $entry): AuditEntryData
    {
        return new AuditEntryData(
            id: (string) $entry->getKey(),
            organizationId: $entry->organization_id,
            actorId: $entry->actor_id,
            actorType: $entry->actor_type ?? AuditActorType::User,
            actingForUserId: $entry->acting_for_user_id,
            action: $entry->action,
            auditableType: $entry->auditable_type,
            auditableId: $entry->auditable_id,
            oldValues: $entry->old_values,
            newValues: $entry->new_values,
            reason: $entry->reason,
            ipAddress: $entry->ip_address,
            correlationId: $entry->correlation_id,
            createdAt: optional($entry->created_at)->toIso8601String() ?? now()->toIso8601String(),
        );
    }
}
