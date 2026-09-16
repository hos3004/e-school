<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Enrollments\Domain\Contracts\EnrollmentAdministrationQueries;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Sessions\Domain\Contracts\SessionSchedulingGateway;
use Modules\Sessions\Domain\ValueObjects\ScheduledParticipantData;
use Shared\Support\BusinessRuleViolation;

/**
 * حصة إضافية يطلبها المشرف خارج أي جدول دائم — لمجموعة، أو للقرآن الفردي،
 * أو لأي كورس آخر. لا تمر بموافقة أحد؛ تُدرَج مباشرة عبر
 * `SessionSchedulingGateway::scheduleExtraSession()` بنفس حراس التعارض
 * والحجز المزدوج التي تحمي كل مسارات الجدولة الأخرى.
 *
 * القرار المالي جزء من الطلب لا تفصيل لاحق: يحدَّد وقت الإنشاء هل للحصة
 * مستحقات للمعلم أصلًا، وإن وُجدت فهل بنفس سعر الحصة العادية (تُترك فارغة
 * ليحلّها `TeacherRateResolver` كأي حصة) أو بسعر مخصّص يُثبَّت في الحصة نفسها.
 */
final readonly class CreateExtraSessionAction
{
    public function __construct(
        private SessionSchedulingGateway $sessions,
        private AcademicCatalogQueries $academics,
        private GroupAdministrationQueries $groups,
        private EnrollmentAdministrationQueries $enrollments,
    ) {}

    public function execute(
        string $organizationId,
        ?string $groupId,
        ?string $studentProfileId,
        string $courseId,
        string $staffProfileId,
        CarbonImmutable $startsAt,
        int $durationMinutes,
        bool $payrollExempt,
        ?int $payrollRateOverrideMinorUnits,
        string $actorId,
        string $reason,
    ): string {
        $reason = trim($reason);
        if ($reason === '') {
            throw BusinessRuleViolation::make('scheduling.reason_required', 'scheduling::errors.reason_required');
        }

        if (($groupId === null) === ($studentProfileId === null)) {
            throw BusinessRuleViolation::make(
                'scheduling.extra_session_target_invalid',
                'scheduling::errors.extra_session_target_invalid',
            );
        }

        if ($durationMinutes <= 0) {
            throw BusinessRuleViolation::make(
                'scheduling.invalid_duration',
                'scheduling::errors.invalid_duration',
            );
        }

        /*
         * سعر مخصّص لا معنى له بلا مستحقات أصلًا؛ لا نخمّن نيّة المستخدم بتجاهله
         * صامتًا، فذلك قد يُظهر لاحقًا أن السعر أُهمل بينما القصد إعفاء الحصة.
         */
        if ($payrollExempt && $payrollRateOverrideMinorUnits !== null) {
            throw BusinessRuleViolation::make(
                'scheduling.extra_session_payroll_conflict',
                'scheduling::errors.extra_session_payroll_conflict',
            );
        }

        if ($payrollRateOverrideMinorUnits !== null && $payrollRateOverrideMinorUnits <= 0) {
            throw BusinessRuleViolation::make(
                'scheduling.invalid_payroll_override',
                'scheduling::errors.invalid_payroll_override',
            );
        }

        $course = $this->academics->coursesByIds($organizationId, [$courseId])[$courseId] ?? null;
        if ($course === null || $course->programId === null) {
            throw BusinessRuleViolation::make('scheduling.course_not_found', 'scheduling::errors.course_not_found');
        }

        $participants = $groupId !== null
            ? $this->groupParticipants($organizationId, $groupId, $course->programId)
            : $this->individualParticipant($organizationId, (string) $studentProfileId, $course->programId);

        $endsAt = $startsAt->addMinutes($durationMinutes);

        /*
         * النوع يُشتق من الوجهة لا يُؤخذ من مدخل الطالب، فلا يمكن طلب حصة
         * لمجموعة بنوع "individual" أو العكس — تمامًا كما تفعل جدولة الحصة
         * الدائمة في `CreateScheduleAction`.
         */
        $sessionType = $groupId !== null ? 'group' : 'individual';

        return $this->sessions->scheduleExtraSession(
            organizationId: $organizationId,
            groupId: $groupId,
            courseId: $courseId,
            staffProfileId: $staffProfileId,
            sessionType: $sessionType,
            startsAt: $startsAt,
            endsAt: $endsAt,
            title: $course->name,
            participants: $participants,
            payrollExempt: $payrollExempt,
            payrollRateOverrideMinorUnits: $payrollRateOverrideMinorUnits,
            actorId: $actorId,
            reason: $reason,
        );
    }

    /** @return list<ScheduledParticipantData> */
    private function individualParticipant(string $organizationId, string $studentProfileId, string $programId): array
    {
        $enrollments = $this->enrollments->schedulableEnrollmentIdsByStudent(
            $organizationId,
            $programId,
            [$studentProfileId],
        );

        if (!isset($enrollments[$studentProfileId])) {
            throw BusinessRuleViolation::make('scheduling.student_not_schedulable', 'scheduling::errors.student_not_schedulable');
        }

        return [new ScheduledParticipantData($studentProfileId, $enrollments[$studentProfileId])];
    }

    /** @return list<ScheduledParticipantData> */
    private function groupParticipants(string $organizationId, string $groupId, string $programId): array
    {
        $members = $this->groups->membershipsForGroup($organizationId, $groupId);
        $studentIds = collect($members)
            ->filter(static fn ($member): bool => $member->status === 'active' && $member->leftAt === null)
            ->map(static fn ($member): string => $member->studentProfileId)
            ->values()
            ->all();

        if ($studentIds === []) {
            throw BusinessRuleViolation::make('scheduling.group_has_no_active_members', 'scheduling::errors.group_has_no_active_members');
        }

        $enrollments = $this->enrollments->schedulableEnrollmentIdsByStudent($organizationId, $programId, $studentIds);

        $participants = [];
        foreach ($studentIds as $studentId) {
            if (!isset($enrollments[$studentId])) {
                continue;
            }

            $participants[] = new ScheduledParticipantData($studentId, $enrollments[$studentId]);
        }

        if ($participants === []) {
            throw BusinessRuleViolation::make('scheduling.group_has_no_active_members', 'scheduling::errors.group_has_no_active_members');
        }

        return $participants;
    }
}
