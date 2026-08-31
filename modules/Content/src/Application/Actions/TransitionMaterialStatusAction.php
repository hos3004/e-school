<?php

declare(strict_types=1);

namespace Modules\Content\Application\Actions;

use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Content\Application\Services\MaterialVersionRecorder;
use Modules\Content\Domain\Enums\MaterialStatus;
use Modules\Content\Domain\Models\CourseMaterial;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class TransitionMaterialStatusAction
{
    public function __construct(
        private Transaction $transaction,
        private MaterialVersionRecorder $versions,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        CourseMaterial $material,
        MaterialStatus $target,
        string $reason,
        ?string $actorId = null,
    ): CourseMaterial {
        $reason = trim($reason);
        if ($reason === '') {
            throw BusinessRuleViolation::make('content.reason_required', 'content::errors.reason_required');
        }

        $from = $material->status;
        if (!$from->canTransitionTo($target)) {
            throw BusinessRuleViolation::make(
                'content.invalid_status_transition',
                'content::errors.invalid_status_transition',
                ['from' => $from->label(), 'to' => $target->label()],
            );
        }

        return $this->transaction->run(function () use ($material, $from, $target, $reason, $actorId): CourseMaterial {
            $material->status = $target;
            $material->revision = (int) $material->revision + 1;

            if ($target === MaterialStatus::Published) {
                $material->published_at = now()->utc();
                $material->published_by = $actorId;
            }

            $material->save();
            $this->versions->record($material, $reason, $actorId);
            $this->audit->record(
                organizationId: (string) $material->organization_id,
                actorId: $actorId,
                actorType: $actorId === null ? 'system' : 'user',
                action: $target === MaterialStatus::Published
                    ? 'content.material_published'
                    : 'content.material_unpublished',
                auditableType: 'course_materials',
                auditableId: (string) $material->getKey(),
                oldValues: ['status' => $from->value],
                newValues: [
                    'status' => $target->value,
                    'revision' => (int) $material->revision,
                    'published_at' => $material->published_at?->toIso8601String(),
                ],
                reason: $reason,
            );

            return $material->refresh();
        });
    }
}
