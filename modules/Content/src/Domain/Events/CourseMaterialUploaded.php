<?php

declare(strict_types=1);

namespace Modules\Content\Domain\Events;

/**
 * رُفعت مادة تعليمية جديدة وأُضيفت لكورس.
 */
final class CourseMaterialUploaded extends CourseMaterialEvent
{
    public function __construct(
        string $materialId,
        string $courseId,
        public readonly string $type,
        public readonly ?string $uploadedBy,
        public readonly ?int $sizeBytes,
        public readonly ?string $visibleFrom,
        public readonly ?string $visibleTo,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($materialId, $courseId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'content.material_uploaded';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'material_id' => $this->materialId,
            'course_id' => $this->courseId,
            'type' => $this->type,
            'uploaded_by' => $this->uploadedBy,
            'size_bytes' => $this->sizeBytes,
            'visible_from' => $this->visibleFrom,
            'visible_to' => $this->visibleTo,
        ];
    }
}
