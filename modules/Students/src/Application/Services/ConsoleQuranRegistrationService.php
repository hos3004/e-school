<?php

declare(strict_types=1);

namespace Modules\Students\Application\Services;

use Illuminate\Support\Facades\Gate;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Models\RegistrationApplication;

/** Quran admission projection and resource authorization, owned by Students. */
final readonly class ConsoleQuranRegistrationService
{
    /** @return list<array{id:string, student_id:string}> */
    public function waiting(string $organizationId, string $courseId, ?string $selectedId = null): array
    {
        return RegistrationApplication::query()->forOrganization($organizationId)
            ->where('preferred_course_id', $courseId)->where(fn ($query) => $query->where('status', RegistrationStatus::WaitingAssignment)->when($selectedId !== null, fn ($query) => $query->orWhere(fn ($query) => $query->whereKey($selectedId)->where('status', RegistrationStatus::Assigned))))
            ->whereNotNull('student_profile_id')->latest('created_at')->get()
            ->map(static fn (RegistrationApplication $item): array => ['id' => $item->id, 'student_id' => (string) $item->student_profile_id])->all();
    }

    public function authorizePlacement(string $organizationId, string $studentId, string $applicationId, string $programId, string $courseId): void
    {
        $application = RegistrationApplication::query()->forOrganization($organizationId)->where('student_profile_id', $studentId)
            ->where('preferred_course_id', $courseId)->where('preferred_program_id', $programId)->lockForUpdate()->findOrFail($applicationId);
        Gate::authorize('scheduleIndividual', $application);
    }
}
