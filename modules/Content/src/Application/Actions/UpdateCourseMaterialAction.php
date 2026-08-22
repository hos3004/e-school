<?php

declare(strict_types=1);

namespace Modules\Content\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
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
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function execute(string $materialId, array $data, ?string $actorId = null): CourseMaterial
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

        $type = isset($data['type']) ? MaterialType::from($data['type']) : $material->type;

        if ($type->requiresFile()
            && (blank($data['disk'] ?? $material->disk) || blank($data['path'] ?? $material->path))) {
            throw BusinessRuleViolation::make(
                'content.file_requires_storage',
                'content::errors.file_requires_storage',
            );
        }

        if ($type->requiresExternalUrl() && blank($data['external_url'] ?? $material->external_url)) {
            throw BusinessRuleViolation::make(
                'content.link_requires_url',
                'content::errors.link_requires_url',
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

        /** @var array{material: CourseMaterial, event: CourseMaterialUpdated} $result */
        $result = $this->transaction->run(function () use ($material, $data, $type, $newFrom, $newTo, $actorId): array {
            $fillable = [
                'title' => $data['title'] ?? $material->title,
                'type' => $type,
                'disk' => $type->requiresFile() ? ($data['disk'] ?? $material->disk) : null,
                'path' => $type->requiresFile() ? ($data['path'] ?? $material->path) : null,
                'external_url' => $type->requiresExternalUrl() ? ($data['external_url'] ?? $material->external_url) : null,
                'size_bytes' => isset($data['size_bytes']) ? (int) $data['size_bytes'] : $material->size_bytes,
                'visible_from' => $newFrom,
                'visible_to' => $newTo,
            ];

            $changed = [];
            foreach ($fillable as $field => $value) {
                if (!$this->sameValue($material->getAttribute($field), $value)) {
                    $changed[] = $field;
                }
            }

            $material->fill($fillable);
            $material->save();

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

        $this->events->dispatch($result['event']);

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
