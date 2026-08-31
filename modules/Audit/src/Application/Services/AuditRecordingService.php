<?php

declare(strict_types=1);

namespace Modules\Audit\Application\Services;

use Modules\Audit\Application\Actions\RecordAuditEntryAction;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Audit\Domain\Enums\AuditActorType;

final readonly class AuditRecordingService implements AuditRecorder
{
    public function __construct(private RecordAuditEntryAction $record) {}

    public function record(
        ?string $organizationId,
        ?string $actorId,
        string $actorType,
        string $action,
        string $auditableType,
        ?string $auditableId,
        ?array $oldValues,
        ?array $newValues,
        ?string $reason,
        ?string $correlationId = null,
    ): string {
        $entry = $this->record->execute(
            organizationId: $organizationId,
            actorId: $actorId,
            actorType: AuditActorType::from($actorType),
            action: $action,
            auditableType: $auditableType,
            auditableId: $auditableId,
            oldValues: $oldValues,
            newValues: $newValues,
            reason: $reason,
            correlationId: $correlationId,
        );

        return (string) $entry->getKey();
    }
}
