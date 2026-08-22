<?php

declare(strict_types=1);

namespace Modules\Content\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
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
    ) {}

    public function execute(string $materialId, string $reason, ?string $actorId = null): void
    {
        /** @var CourseMaterial|null $material */
        $material = CourseMaterial::query()->find($materialId);

        if ($material === null) {
            throw BusinessRuleViolation::make(
                'content.material_not_found',
                'content::errors.material_not_found',
                ['material_id' => $materialId],
            );
        }

        if ($reason === '') {
            throw BusinessRuleViolation::make(
                'content.removal_reason_required',
                'content::errors.removal_reason_required',
            );
        }

        [$courseId, $event] = $this->transaction->run(function () use ($material, $reason, $actorId): array {
            $material->delete();

            return [
                $material->course_id,
                new CourseMaterialRemoved(
                    materialId: $material->id,
                    courseId: $material->course_id,
                    reason: $reason,
                    actorId: $actorId,
                ),
            ];
        });

        unset($courseId);
        $this->events->dispatch($event);
    }
}
