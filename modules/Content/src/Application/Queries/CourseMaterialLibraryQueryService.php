<?php

declare(strict_types=1);

namespace Modules\Content\Application\Queries;

use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Content\Domain\Contracts\CourseMaterialLibraryQueries;
use Modules\Content\Domain\Models\CourseMaterial;
use Modules\Content\Domain\ValueObjects\PublishedCourseMaterialData;

final readonly class CourseMaterialLibraryQueryService implements CourseMaterialLibraryQueries
{
    public function __construct(private AcademicCatalogQueries $academics) {}

    public function publishedForCourses(string $organizationId, array $authorizedCourseIds): array
    {
        $courses = array_keys($this->academics->coursesByIds($organizationId, $authorizedCourseIds));
        if ($courses === []) {
            return [];
        }

        return CourseMaterial::query()->forOrganization($organizationId)->whereIn('course_id', $courses)->active()
            ->orderBy('display_order')->orderByDesc('published_at')->orderBy('id')
            ->get(['id', 'course_id', 'title', 'description', 'type', 'size_bytes', 'revision', 'published_at'])
            ->map(static fn (CourseMaterial $material): PublishedCourseMaterialData => new PublishedCourseMaterialData(
                (string) $material->id, (string) $material->course_id, $material->title, $material->description ?? [],
                $material->type->value, $material->size_bytes, $material->revision, $material->published_at?->toIso8601String(),
            ))->all();
    }
}
