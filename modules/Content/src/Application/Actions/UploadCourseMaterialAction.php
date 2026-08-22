<?php

declare(strict_types=1);

namespace Modules\Content\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Content\Domain\Enums\MaterialType;
use Modules\Content\Domain\Events\CourseMaterialUploaded;
use Modules\Content\Domain\Models\CourseMaterial;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * رفع مادة تعليمية جديدة وإضافتها لكورس.
 *
 * الترتيب إلزامي: حراس ← معاملة قاعدة البيانات ← نشر الحدث بعد النجاح.
 */
final readonly class UploadCourseMaterialAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function execute(array $data, ?string $actorId = null): CourseMaterial
    {
        $type = MaterialType::from($data['type']);

        if ($type->requiresFile()) {
            if (blank($data['disk'] ?? null) || blank($data['path'] ?? null)) {
                throw BusinessRuleViolation::make(
                    'content.file_requires_storage',
                    'content::errors.file_requires_storage',
                );
            }

            $extension = strtolower(pathinfo((string) $data['path'], PATHINFO_EXTENSION));
            /** @var list<string> $allowed */
            $allowed = config('content.uploads.allowed_extensions', []);

            if (!in_array($extension, $allowed, true)) {
                throw BusinessRuleViolation::make(
                    'content.extension_not_allowed',
                    'content::errors.extension_not_allowed',
                    ['extension' => $extension],
                );
            }
        }

        if ($type->requiresExternalUrl() && blank($data['external_url'] ?? null)) {
            throw BusinessRuleViolation::make(
                'content.link_requires_url',
                'content::errors.link_requires_url',
            );
        }

        $sizeBytes = isset($data['size_bytes']) ? (int) $data['size_bytes'] : null;

        if ($sizeBytes !== null) {
            $maxSizeMb = (int) config('content.uploads.max_size_mb', 100);
            $maxBytes = $maxSizeMb * 1024 * 1024;

            if ($sizeBytes > $maxBytes) {
                throw BusinessRuleViolation::make(
                    'content.file_too_large',
                    'content::errors.file_too_large',
                    ['max_mb' => $maxSizeMb],
                );
            }
        }

        $visibleFrom = isset($data['visible_from'])
            ? CarbonImmutable::parse($data['visible_from'], 'UTC')
            : null;
        $visibleTo = isset($data['visible_to'])
            ? CarbonImmutable::parse($data['visible_to'], 'UTC')
            : null;

        if ($visibleFrom !== null && $visibleTo !== null && $visibleTo->lessThanOrEqualTo($visibleFrom)) {
            throw BusinessRuleViolation::make(
                'content.visibility_window_invalid',
                'content::errors.visibility_window_invalid',
            );
        }

        /** @var array{material: CourseMaterial, event: CourseMaterialUploaded} $result */
        $result = $this->transaction->run(function () use ($data, $type, $sizeBytes, $visibleFrom, $visibleTo, $actorId): array {
            $material = new CourseMaterial;
            $material->fill([
                ...$data,
                'type' => $type,
                'disk' => $type->requiresFile() ? (string) $data['disk'] : null,
                'path' => $type->requiresFile() ? (string) $data['path'] : null,
                'external_url' => $type->requiresExternalUrl() ? (string) $data['external_url'] : null,
                'size_bytes' => $sizeBytes,
                'visible_from' => $visibleFrom,
                'visible_to' => $visibleTo,
                'uploaded_by' => $actorId ?? auth()->id(),
            ]);
            $material->save();

            return [
                'material' => $material,
                'event' => new CourseMaterialUploaded(
                    materialId: $material->id,
                    courseId: $material->course_id,
                    type: $type->value,
                    uploadedBy: $material->uploaded_by,
                    sizeBytes: $sizeBytes,
                    visibleFrom: $visibleFrom?->toIso8601String(),
                    visibleTo: $visibleTo?->toIso8601String(),
                    actorId: $actorId,
                ),
            ];
        });

        $this->events->dispatch($result['event']);

        return $result['material'];
    }
}
