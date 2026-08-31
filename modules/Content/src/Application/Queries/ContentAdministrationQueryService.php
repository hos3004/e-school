<?php

declare(strict_types=1);

namespace Modules\Content\Application\Queries;

use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Academics\Domain\ValueObjects\AcademicCatalogItemData;
use Modules\Content\Domain\Models\CourseMaterial;
use Modules\Content\Domain\Models\CourseMaterialVersion;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;

final readonly class ContentAdministrationQueryService
{
    public function __construct(
        private AcademicCatalogQueries $academics,
        private UserAccountDirectory $accounts,
    ) {}

    /** @return array<string, string> */
    public function courseOptions(string $organizationId): array
    {
        $options = [];

        foreach ($this->academics->programs($organizationId) as $program) {
            foreach ($this->academics->courses($organizationId, $program->id) as $course) {
                $options[$course->id] = $this->catalogLabel($program).' — '.$this->catalogLabel($course);
            }
        }

        return $options;
    }

    public function courseLabel(string $organizationId, string $courseId): string
    {
        $course = $this->academics->coursesByIds($organizationId, [$courseId])[$courseId] ?? null;

        return $course === null
            ? __('content::messages.not_available')
            : $this->catalogLabel($course);
    }

    /** @return list<array<string, mixed>> */
    public function versions(string $organizationId, string $materialId): array
    {
        /** @var CourseMaterial|null $material */
        $material = CourseMaterial::query()
            ->forOrganization($organizationId)
            ->whereKey($materialId)
            ->first();

        if ($material === null) {
            return [];
        }

        $versions = CourseMaterialVersion::query()
            ->where('material_id', $materialId)
            ->latest('revision')
            ->get();
        $actors = $this->accounts->findMany(
            $organizationId,
            $versions->pluck('changed_by')->filter()->map(static fn (mixed $id): string => (string) $id)->all(),
        );

        return $versions->map(static function (CourseMaterialVersion $version) use ($actors): array {
            $snapshot = $version->snapshot;
            $status = is_string($snapshot['status'] ?? null) ? $snapshot['status'] : 'draft';

            return [
                'id' => (string) $version->getKey(),
                'revision' => (int) $version->revision,
                'status' => __('content::status.'.$status),
                'reason' => (string) $version->reason,
                'actor' => $version->changed_by === null
                    ? __('content::messages.system')
                    : ($actors[$version->changed_by]->name ?? __('content::messages.system')),
                'created_at' => $version->created_at->toIso8601String(),
            ];
        })->values()->all();
    }

    private function catalogLabel(AcademicCatalogItemData $item): string
    {
        return $this->localized($item->name).' · '.$item->code;
    }

    /** @param array<string, string> $value */
    private function localized(array $value): string
    {
        return $value[app()->getLocale()] ?? $value['ar'] ?? $value['en'] ?? (string) reset($value);
    }
}
