<?php

declare(strict_types=1);

namespace Modules\Audit\Domain\Contracts;

/** منفذ الكتابة الوحيد المعلن لسجل التدقيق؛ لا يسرّب نموذج Audit. */
interface AuditRecorder
{
    /**
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
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
    ): string;
}
