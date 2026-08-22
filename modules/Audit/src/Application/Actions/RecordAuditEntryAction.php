<?php

declare(strict_types=1);

namespace Modules\Audit\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Audit\Domain\Enums\AuditActorType;
use Modules\Audit\Domain\Events\AuditEntryRecorded;
use Modules\Audit\Domain\Models\AuditLog;
use Shared\Support\BusinessRuleViolation;

/**
 * تسجيل قيدة تدقيق جديدة — عملية الكتابة الوحيدة على دفتر التدقيق.
 *
 * الترتيب الإلزامي داخل execute: حراس ← DB::transaction ← نشر الحدث بعد النجاح.
 */
final readonly class RecordAuditEntryAction
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function execute(
        ?string $organizationId,
        ?string $actorId,
        AuditActorType $actorType,
        string $action,
        string $auditableType,
        ?string $auditableId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?string $actingForUserId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $correlationId = null,
    ): AuditLog {
        $this->guard($action, $reason);

        $action = strtolower(trim($action));
        $correlationId = $correlationId !== null && $correlationId !== ''
            ? $correlationId
            : (string) Str::ulid();

        /** @var AuditLog $entry */
        $entry = DB::transaction(function () use (
            $organizationId, $actorId, $actorType, $action, $auditableType,
            $auditableId, $oldValues, $newValues, $reason,
            $actingForUserId, $ipAddress, $userAgent, $correlationId,
        ): AuditLog {
            return AuditLog::query()->create([
                'organization_id' => $organizationId,
                'actor_id' => $actorId,
                'actor_type' => $actorType,
                'acting_for_user_id' => $actingForUserId,
                'action' => $action,
                'auditable_type' => $auditableType,
                'auditable_id' => $auditableId,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'reason' => $reason,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent !== null ? Str::limit($userAgent, 512, '') : null,
                'correlation_id' => $correlationId,
                'created_at' => now()->utc(),
            ]);
        });

        Event::dispatch(new AuditEntryRecorded(
            auditLogId: (string) $entry->getKey(),
            organizationId: $entry->organization_id,
            actorId: $entry->actor_id,
            actorType: $entry->actor_type ?? $actorType,
            action: $entry->action,
            auditableType: $entry->auditable_type,
            auditableId: $entry->auditable_id,
            oldValues: $entry->old_values,
            newValues: $entry->new_values,
            actingForUserId: $entry->acting_for_user_id,
            correlationId: $entry->correlation_id,
        ));

        return $entry;
    }

    /**
     * الأفعال الحساسة (حضور/مال/صلاحيات/تسجيلات...) تتطلب سببًا مكتوبًا.
     * القائمة من config — لا أرقام ولا أنماط داخل الكود.
     */
    private function guard(string $action, ?string $reason): void
    {
        $normalized = strtolower(trim($action));

        if ($normalized === '') {
            throw BusinessRuleViolation::make(
                'audit.action_required',
                'audit::errors.action_required',
            );
        }

        if ($reason !== null && trim($reason) !== '') {
            return;
        }

        foreach ((array) config('audit.reason_required_actions', []) as $pattern) {
            if (Str::is((string) $pattern, $normalized)) {
                throw BusinessRuleViolation::make(
                    'audit.reason_required',
                    'audit::errors.reason_required',
                    ['action' => $normalized],
                );
            }
        }
    }
}
