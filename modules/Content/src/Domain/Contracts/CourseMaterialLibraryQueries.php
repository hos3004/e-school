<?php

declare(strict_types=1);

namespace Modules\Content\Domain\Contracts;

use Modules\Content\Domain\ValueObjects\PublishedCourseMaterialData;

interface CourseMaterialLibraryQueries
{
    /**
     * Authorized course IDs are resolved by the application composition layer, never taken from user input.
     *
     * @param list<string> $authorizedCourseIds
     * @return list<PublishedCourseMaterialData>
     */
    public function publishedForCourses(string $organizationId, array $authorizedCourseIds): array;
}
