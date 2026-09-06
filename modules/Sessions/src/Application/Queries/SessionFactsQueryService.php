<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Queries;

use Carbon\CarbonImmutable;
use Modules\Sessions\Domain\Contracts\SessionFactsQueries;
use Modules\Sessions\Domain\Enums\ApologyStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\TeacherApology;
use Modules\Sessions\Domain\ValueObjects\SessionPayrollFacts;

final readonly class SessionFactsQueryService implements SessionFactsQueries
{
    public function payrollFactsFor(string $sessionId): ?SessionPayrollFacts
    {
        /** @var Session|null $session */
        $session = Session::query()->whereKey($sessionId)->first();

        if ($session === null) {
            return null;
        }

        /*
         * `original_teacher_id` هو المسند أصلًا و`staff_profile_id` هو المنفّذ.
         * التفريق مقصود: عند وجود بديل تُحتسب الحصة للمنفّذ بأجره هو، ويُخصم
         * من الأساسي وفق `config/payroll.php → substitution`.
         */
        return new SessionPayrollFacts(
            sessionId: (string) $session->getKey(),
            organizationId: (string) $session->organization_id,
            courseId: (string) $session->course_id,
            groupId: $session->group_id === null ? null : (string) $session->group_id,
            staffProfileId: (string) $session->staff_profile_id,
            originalTeacherId: (string) ($session->original_teacher_id ?? $session->staff_profile_id),
            sessionType: (string) $session->session_type,
            status: $session->status->value,
            scheduledStart: CarbonImmutable::parse((string) $session->scheduled_start)->utc(),
            scheduledEnd: CarbonImmutable::parse((string) $session->scheduled_end)->utc(),
            makeupForSessionId: $session->makeup_for_session_id === null
                ? null
                : (string) $session->makeup_for_session_id,
            hasApprovedTeacherApology: TeacherApology::query()
                ->where('session_id', $session->getKey())
                ->whereIn('status', [
                    ApologyStatus::Approved,
                    ApologyStatus::Covered,
                ])
                ->exists(),
            hasStudentApology: $session->participants()
                ->whereNotNull('excused_at')
                ->exists(),
        );
    }
}
