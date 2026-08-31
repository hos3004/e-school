<?php

declare(strict_types=1);

namespace Modules\Content\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Content\Application\Services\MaterialVersionRecorder;
use Modules\Content\Domain\Enums\MaterialType;
use Modules\Content\Domain\Events\CourseMaterialUpdated;
use Modules\Content\Domain\Models\CourseMaterial;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تعديل مادة تعليمية قائمة — العنوان أو النوع أو الملف أو نافذة الظهور.
 */
final readonly class UpdateCourseMaterialAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private MaterialVersionRecorder $versions,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function execute(CourseMaterial $material, array $data, string $reason, ?string $actorId = null): CourseMaterial
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw BusinessRuleViolation::make('content.reason_required', 'content::errors.reason_required');
        }

        $data = Arr::except($data, ['organization_id', 'course_id', 'status', 'revision', 'reason']);

        $type = isset($data['type']) ? MaterialType::from($data['type']) : $material->type;

        if ($type->requiresFile()
            && (blank($data['disk'] ?? $material->disk) || blank($data['path'] ?? $material->path))) {
            throw BusinessRuleViolation::make(
                'content.file_requires_storage',
                'content::errors.file_requires_storage',
            );
        }

        if ($type->requiresFile()) {
            $path = (string) ($data['path'] ?? $material->path);
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
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

        if ($type->requiresExternalUrl() && blank($data['external_url'] ?? $material->external_url)) {
            throw BusinessRuleViolation::make(
                'content.link_requires_url',
                'content::errors.link_requires_url',
            );
        }

        $sizeBytes = array_key_exists('size_bytes', $data) && $data['size_bytes'] !== null
            ? (int) $data['size_bytes']
            : $material->size_bytes;
        $maxSizeMb = (int) config('content.uploads.max_size_mb');
        if ($sizeBytes !== null && $sizeBytes > $maxSizeMb * 1024 * 1024) {
            throw BusinessRuleViolation::make(
                'content.file_too_large',
                'content::errors.file_too_large',
                ['max_mb' => $maxSizeMb],
            );
        }

        // نافذة الظهور الفعلية بعد التعديل: القيمة الجديدة إن وُجدت، وإلا الحالية.
        $fromProvided = array_key_exists('visible_from', $data);
        $toProvided = array_key_exists('visible_to', $data);

        $newFrom = $fromProvided && $data['visible_from'] !== null
            ? CarbonImmutable::parse((string) $data['visible_from'], 'UTC')
            : ($fromProvided ? null : $material->visible_from);
        $newTo = $toProvided && $data['visible_to'] !== null
            ? CarbonImmutable::parse((string) $data['visible_to'], 'UTC')
            : ($toProvided ? null : $material->visible_to);

        if ($newFrom !== null && $newTo !== null && $newTo->lessThanOrEqualTo($newFrom)) {
            throw BusinessRuleViolation::make(
                'content.visibility_window_invalid',
                'content::errors.visibility_window_invalid',
            );
        }

        /** @var array{material: CourseMaterial, event: CourseMaterialUpdated|null} $result */
        $result = $this->transaction->run(function () use ($material, $data, $type, $newFrom, $newTo, $sizeBytes, $actorId, $reason): array {
            $fillable = [
                'title' => $data['title'] ?? $material->title,
                'description' => $data['description'] ?? $material->description,
                'type' => $type,
                'display_order' => isset($data['display_order']) ? (int) $data['display_order'] : $material->display_order,
                'disk' => $type->requiresFile() ? ($data['disk'] ?? $material->disk) : null,
                'path' => $type->requiresFile() ? ($data['path'] ?? $material->path) : null,
                'external_url' => $type->requiresExternalUrl() ? ($data['external_url'] ?? $material->external_url) : null,
                'size_bytes' => $type->requiresFile() ? $sizeBytes : null,
                'visible_from' => $newFrom,
                'visible_to' => $newTo,
            ];

            $changed = [];
            foreach ($fillable as $field => $value) {
                if (!$this->sameValue($material->getAttribute($field), $value)) {
                    $changed[] = $field;
                }
            }

            if ($changed === []) {
                return ['material' => $material, 'event' => null];
            }

            $oldValues = Arr::only($material->getAttributes(), $changed);

            $material->fill($fillable);
            $material->revision = (int) $material->revision + 1;
            $material->save();

            $this->versions->record($material, $reason, $actorId);
            $this->audit->record(
                organizationId: (string) $material->organization_id,
                actorId: $actorId,
                actorType: $actorId === null ? 'system' : 'user',
                action: 'content.material_updated',
                auditableType: 'course_materials',
                auditableId: (string) $material->getKey(),
                oldValues: $oldValues,
                newValues: [
                    ...Arr::only($material->getAttributes(), $changed),
                    'revision' => (int) $material->revision,
                ],
                reason: $reason,
            );

            return [
                'material' => $material,
                'event' => new CourseMaterialUpdated(
                    materialId: $material->id,
                    courseId: $material->course_id,
                    changed: $changed,
                    actorId: $actorId,
                ),
            ];
        });

        if ($result['event'] !== null) {
            $this->events->dispatch($result['event']);
        }

        return $result['material'];
    }

    private function sameValue(mixed $current, mixed $incoming): bool
    {
        if ($current instanceof \BackedEnum) {
            $current = $current->value;
        } elseif ($current instanceof CarbonImmutable) {
            $current = $current->toISOString();
        } elseif (is_array($current)) {
            $current = json_encode($current, JSON_UNESCAPED_UNICODE);
            $incoming = is_array($incoming) ? json_encode($incoming, JSON_UNESCAPED_UNICODE) : $incoming;
        }

        return $current == $incoming;
    }
}
