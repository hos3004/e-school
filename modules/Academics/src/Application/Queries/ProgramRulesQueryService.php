<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Queries;

use Modules\Academics\Domain\Contracts\ProgramRulesQueries;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Program;
use Modules\Academics\Domain\Models\ProgramEligibility;
use Modules\Academics\Domain\ValueObjects\PlacementAcademicContext;
use Modules\Academics\Domain\ValueObjects\ProgramEligibilityData;

final class ProgramRulesQueryService implements ProgramRulesQueries
{
    public function eligibilityOf(string $programId): ?ProgramEligibilityData
    {
        /** @var ProgramEligibility|null $eligibility */
        $eligibility = ProgramEligibility::query()
            ->where('program_id', $programId)
            ->first();

        if ($eligibility === null) {
            return null;
        }

        return new ProgramEligibilityData(
            programId: $eligibility->program_id,
            countries: is_array($eligibility->countries) ? array_values($eligibility->countries) : [],
            regions: is_array($eligibility->regions) ? array_values($eligibility->regions) : [],
            ageFrom: $eligibility->age_from,
            ageTo: $eligibility->age_to,
            gender: $eligibility->gender?->value,
            manualApprovalRequired: $eligibility->manual_approval_required,
            teacherGenderRule: $eligibility->teacher_gender_rule ?? 'any',
            requiresIndividualSessions: $eligibility->requires_individual_sessions,
        );
    }

    public function teacherGenderRule(string $programId): string
    {
        $rule = ProgramEligibility::query()
            ->where('program_id', $programId)
            ->value('teacher_gender_rule');

        return is_string($rule) && $rule !== '' ? $rule : (string) config('admission.matching.default_gender_rule', 'any');
    }

    public function programIdsOfCourse(string $courseId): array
    {
        /** @var Course|null $course */
        $course = Course::query()->with('level')->find($courseId);

        if ($course === null || $course->level === null) {
            return [];
        }

        return [$course->level->program_id];
    }

    public function sessionModeOfCourse(string $courseId): ?string
    {
        /** @var Course|null $course */
        $course = Course::query()->find($courseId);

        return $course?->session_mode?->value;
    }

    public function placementContext(
        string $organizationId,
        string $programId,
        ?string $courseId,
    ): ?PlacementAcademicContext {
        $programExists = Program::query()
            ->forOrganization($organizationId)
            ->active()
            ->whereKey($programId)
            ->exists();

        if (!$programExists) {
            return null;
        }

        if ($courseId === null) {
            return new PlacementAcademicContext(
                organizationId: $organizationId,
                programId: $programId,
                courseId: null,
                sessionMode: null,
            );
        }

        /** @var Course|null $course */
        $course = Course::query()
            ->forOrganization($organizationId)
            ->active()
            ->with('level')
            ->find($courseId);

        if ($course === null || $course->level === null || (string) $course->level->program_id !== $programId) {
            return null;
        }

        return new PlacementAcademicContext(
            organizationId: $organizationId,
            programId: $programId,
            courseId: $courseId,
            sessionMode: $course->session_mode?->value,
        );
    }
}
