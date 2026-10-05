<?php

declare(strict_types=1);

namespace Modules\Content\Application\Services;

use Modules\Content\Domain\Models\CourseMaterial;
use Modules\Content\Domain\Models\CourseMaterialVersion;

final readonly class MaterialVersionRecorder
{
    public function record(CourseMaterial $material, string $reason, ?string $actorId): CourseMaterialVersion
    {
        return CourseMaterialVersion::query()->create([
            'material_id' => (string) $material->getKey(),
            'revision' => (int) $material->revision,
            'snapshot' => [
                'course_id' => (string) $material->course_id,
                'title' => $material->title,
                'description' => $material->description,
                'type' => $material->type->value,
                'status' => $material->status->value,
                'display_order' => (int) $material->display_order,
                'disk' => $material->disk,
                'path' => $material->path,
                'external_url' => $material->external_url,
                'size_bytes' => $material->size_bytes,
                'visible_from' => $material->visible_from?->toIso8601String(),
                'visible_to' => $material->visible_to?->toIso8601String(),
                'published_at' => $material->published_at?->toIso8601String(),
            ],
            'changed_by' => $actorId,
            'reason' => trim($reason),
            'created_at' => now()->utc(),
        ]);
    }
}
