<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Queries;

use Modules\Academics\Domain\Models\Course;
use Modules\Students\Domain\Contracts\RegistrationOfferingQueries;

final readonly class RegistrationOfferingQueryService implements RegistrationOfferingQueries
{
    public function isAvailable(
        string $organizationId,
        string $programId,
        string $courseId,
    ): bool {
        /** @var Course|null $course */
        $course = Course::query()
            ->forOrganization($organizationId)
            ->active()
            ->with('level.program')
            ->find($courseId);

        return $course !== null
            && $course->level !== null
            && (string) $course->level->program_id === $programId
            && $course->level->program !== null
            && (string) $course->level->program->organization_id === $organizationId
            && (bool) $course->level->program->is_active;
    }
}
