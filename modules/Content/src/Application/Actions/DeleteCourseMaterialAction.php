<?php

declare(strict_types=1);

namespace Modules\Content\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Content\Domain\Events\CourseMaterialRemoved;
use Modules\Content\Domain\Models\CourseMaterial;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إزالة مادة تعليمية من الكورس — تعليق (SoftDelete) لا حذف فعلي.
 */
final readonly class DeleteCourseMaterialAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    public function execute(CourseMaterial $material, string $reason, ?string $actorId = null): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw BusinessRuleViolation::make(
                'content.removal_reason_required',
                'content::errors.removal_reason_required',
            );
        }

        $event = $this->transaction->run(function () use ($material, $reason, $actorId): CourseMaterialRemoved {
            $material->delete();

            $this->audit->record(
                organizationId: (string) $material->organization_id,
                actorId: $actorId,
                actorType: $actorId === null ? 'system' : 'user',
                action: 'content.material_archived',
                auditableType: 'course_materials',
                auditableId: (string) $material->getKey(),
                oldValues: ['deleted_at' => null, 'status' => $material->status->value],
                newValues: ['deleted_at' => $material->deleted_at?->toIso8601String()],
                reason: $reason,
            );

            return new CourseMaterialRemoved(
                materialId: $material->id,
                courseId: $material->course_id,
                reason: $reason,
                actorId: $actorId,
            );
        });

        $this->events->dispatch($event);
    }
}
