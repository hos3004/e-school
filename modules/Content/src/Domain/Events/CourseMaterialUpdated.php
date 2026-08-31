<?php

declare(strict_types=1);

namespace Modules\Content\Domain\Events;

/**
 * عُدّلت مادة تعليمية — بياناتها أو نافذة ظهورها.
 */
final class CourseMaterialUpdated extends CourseMaterialEvent
{
    /**
     * @param list<string> $changed
     */
    public function __construct(
        string $materialId,
        string $courseId,
        public readonly array $changed,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($materialId, $courseId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'content.material_updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'material_id' => $this->materialId,
            'course_id' => $this->courseId,
            'changed_fields' => array_values($this->changed),
        ];
    }
}
