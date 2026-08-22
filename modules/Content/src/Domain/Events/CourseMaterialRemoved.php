<?php

declare(strict_types=1);

namespace Modules\Content\Domain\Events;

/**
 * أُوقفت مادة تعليمية وأُزيلت من الكورس (تعليق — لا حذف فعلي).
 */
final class CourseMaterialRemoved extends CourseMaterialEvent
{
    public function __construct(
        string $materialId,
        string $courseId,
        public readonly string $reason,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($materialId, $courseId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'content.material_removed';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'material_id' => $this->materialId,
            'course_id' => $this->courseId,
            'reason' => $this->reason,
        ];
    }
}
