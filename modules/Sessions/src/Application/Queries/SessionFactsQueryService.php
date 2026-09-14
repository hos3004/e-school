<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Queries;

use Carbon\CarbonImmutable;
use Modules\Sessions\Domain\Contracts\SessionFactsQueries;
use Modules\Sessions\Domain\Enums\ApologyStatus;
use Modules\Sessions\Domain\Enums\SessionStatus;
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

    public function postponedPairs(int $limit, ?string $afterOriginalSessionId = null): array
    {
        $originals = Session::query()
            ->where('status', SessionStatus::Postponed)
            ->when(
                $afterOriginalSessionId !== null && $afterOriginalSessionId !== '',
                static fn ($query) => $query->where('id', '>', $afterOriginalSessionId),
            )
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get(['id', 'organization_id', 'course_id', 'staff_profile_id']);

        if ($originals->isEmpty()) {
            return [];
        }

        $makeups = Session::query()
            ->whereIn('makeup_for_session_id', $originals->pluck('id')->all())
            ->get(['id', 'makeup_for_session_id', 'status', 'scheduled_start', 'scheduled_end'])
            ->keyBy('makeup_for_session_id');

        $pairs = [];

        foreach ($originals as $original) {
            $makeup = $makeups->get((string) $original->getKey());

            /*
             * حصة مؤجَّلة بلا تعويضية ليست زوجًا: لا مفتاح تحرير لها، فلا
             * تُنشأ لها قيدة مؤجَّلة تبقى معلّقة إلى الأبد.
             */
            if ($makeup === null) {
                continue;
            }

            /*
             * تعويضية ألغيت أو استُبدلت لن تُقام أبدًا، فقيدة مؤجَّلة معلَّقة
             * عليها تبقى معلَّقة إلى الأبد بلا مسار تحرير. هذه تسوية إدارية
             * لا معالجة آلية.
             */
            if ($makeup->status->isTerminal()) {
                continue;
            }

            $pairs[] = [
                'original_session_id' => (string) $original->getKey(),
                'makeup_session_id' => (string) $makeup->getKey(),
                'organization_id' => (string) $original->organization_id,
                'course_id' => (string) $original->course_id,
                'staff_profile_id' => (string) $original->staff_profile_id,
                'makeup_start' => CarbonImmutable::parse((string) $makeup->scheduled_start)->utc()->toIso8601String(),
                'makeup_end' => CarbonImmutable::parse((string) $makeup->scheduled_end)->utc()->toIso8601String(),
            ];
        }

        return $pairs;
    }
}
