<?php

declare(strict_types=1);

namespace Modules\Sessions\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Models\Group;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Students\Domain\Models\StudentProfile;
use Tests\TestCase;

/**
 * @phpstan-type ReportingContext array{
 *     teacherOne: StaffProfile,
 *     teacherTwo: StaffProfile,
 *     studentOne: StudentProfile,
 *     studentTwo: StudentProfile,
 *     courseOne: Course,
 *     courseTwo: Course,
 *     groupOne: Group,
 *     groupTwo: Group,
 *     enrollmentOne: Enrollment,
 *     enrollmentTwo: Enrollment
 * }
 */
final class SessionReportingQueriesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_report_query_applies_half_open_period_tenant_filters_order_and_configured_limit(): void
    {
        CarbonImmutable::setTestNow('2026-08-31 06:00:00 UTC');
        $organization = Organization::factory()->create();
        $context = $this->context($organization);
        $from = CarbonImmutable::parse('2026-08-01 00:00:00 UTC');
        $until = CarbonImmutable::parse('2026-09-01 00:00:00 UTC');

        $completed = $this->makeSession($context, [
            'status' => SessionStatus::Completed,
            'session_type' => 'regular',
            'scheduled_start' => $from,
            'scheduled_end' => $from->addHour(),
            'actual_start' => $from->addMinutes(5),
            'actual_end' => $from->addMinutes(55),
            'finalized_at' => $from->addHour(),
        ]);
        $cancelled = $this->makeSession($context, [
            'course_id' => $context['courseTwo']->id,
            'staff_profile_id' => $context['teacherTwo']->id,
            'original_teacher_id' => $context['teacherOne']->id,
            'status' => SessionStatus::CancelledByTeacher,
            'session_type' => 'makeup',
            'scheduled_start' => $from->addDay(),
            'scheduled_end' => $from->addDay()->addHour(),
            'cancellation_reason' => 'عذر صحي موثّق',
        ]);
        $assessment = $this->makeSession($context, [
            'group_id' => $context['groupTwo']->id,
            'original_teacher_id' => $context['teacherTwo']->id,
            'status' => SessionStatus::Scheduled,
            'session_type' => 'assessment',
            'scheduled_start' => $from->addDays(2),
            'scheduled_end' => $from->addDays(2)->addHour(),
        ]);
        $this->makeSession($context, [
            'scheduled_start' => $from->subHour(),
            'scheduled_end' => $from,
        ]);
        $this->makeSession($context, [
            'scheduled_start' => $until,
            'scheduled_end' => $until->addHour(),
        ]);

        $this->participant($completed, $context['studentOne'], $context['enrollmentOne'], 50);
        $this->participant($completed, $context['studentTwo'], $context['enrollmentTwo'], 45);
        $this->participant($cancelled, $context['studentTwo'], $context['enrollmentTwo']);

        $otherContext = $this->context(Organization::factory()->create());
        $otherSession = $this->makeSession($otherContext, [
            'scheduled_start' => $from->addMinutes(30),
            'scheduled_end' => $from->addMinutes(90),
        ]);

        $queries = app(SessionAdministrationQueries::class);

        self::assertSame(
            [$completed->id, $cancelled->id, $assessment->id],
            array_column($queries->forReport((string) $organization->id, $from, $until), 'id'),
        );
        self::assertSame(
            [$cancelled->id],
            array_column($queries->forReport(
                (string) $organization->id,
                $from,
                $until,
                statuses: [SessionStatus::CancelledByTeacher->value],
            ), 'id'),
        );
        self::assertSame(
            [$completed->id],
            array_column($queries->forReport(
                (string) $organization->id,
                $from,
                $until,
                studentProfileId: (string) $context['studentOne']->id,
            ), 'id'),
        );
        self::assertSame(
            [$completed->id, $cancelled->id],
            array_column($queries->forReport(
                (string) $organization->id,
                $from,
                $until,
                studentProfileId: (string) $context['studentTwo']->id,
            ), 'id'),
        );
        self::assertSame(
            [$cancelled->id],
            array_column($queries->forReport(
                (string) $organization->id,
                $from,
                $until,
                staffProfileId: (string) $context['teacherTwo']->id,
            ), 'id'),
        );
        self::assertSame(
            [$completed->id, $cancelled->id],
            array_column($queries->forReport(
                (string) $organization->id,
                $from,
                $until,
                originalStaffProfileId: (string) $context['teacherOne']->id,
            ), 'id'),
        );
        self::assertSame(
            [$assessment->id],
            array_column($queries->forReport(
                (string) $organization->id,
                $from,
                $until,
                groupId: (string) $context['groupTwo']->id,
            ), 'id'),
        );
        self::assertSame(
            [$cancelled->id],
            array_column($queries->forReport(
                (string) $organization->id,
                $from,
                $until,
                courseId: (string) $context['courseTwo']->id,
            ), 'id'),
        );
        self::assertSame(
            [$assessment->id],
            array_column($queries->forReport(
                (string) $organization->id,
                $from,
                $until,
                sessionTypes: ['assessment'],
            ), 'id'),
        );
        self::assertNotContains($otherSession->id, array_column(
            $queries->forReport((string) $organization->id, $from, $until),
            'id',
        ));
        self::assertSame([], $queries->forReport((string) $organization->id, $until, $from));
        self::assertSame(
            [$cancelled->id, $assessment->id],
            array_column($queries->forReport(
                (string) $organization->id,
                $from,
                $until,
                afterScheduledStart: CarbonImmutable::parse((string) $completed->scheduled_start),
                afterId: (string) $completed->id,
            ), 'id'),
        );

        $completedData = $queries->forReport(
            (string) $organization->id,
            $from,
            $until,
            statuses: [SessionStatus::Completed->value],
        )[0];
        self::assertSame((string) $context['teacherOne']->id, $completedData->originalStaffProfileId);
        self::assertSame('regular', $completedData->sessionType);
        self::assertSame($from->addMinutes(5)->toIso8601String(), $completedData->actualStart);
        self::assertSame($from->addMinutes(55)->toIso8601String(), $completedData->actualEnd);
        self::assertSame($from->addHour()->toIso8601String(), $completedData->finalizedAt);

        $cancelledData = $queries->forReport(
            (string) $organization->id,
            $from,
            $until,
            statuses: [SessionStatus::CancelledByTeacher->value],
        )[0];
        self::assertSame('عذر صحي موثّق', $cancelledData->cancellationReason);

        config()->set('sessions.reporting.max_items', 2);
        self::assertSame(
            [$completed->id, $cancelled->id],
            array_column($queries->forReport((string) $organization->id, $from, $until, limit: 50), 'id'),
        );
    }

    public function test_participants_are_loaded_for_multiple_sessions_with_tenant_isolation_and_constant_queries(): void
    {
        CarbonImmutable::setTestNow('2026-08-31 06:00:00 UTC');
        $organization = Organization::factory()->create();
        $context = $this->context($organization);
        $start = CarbonImmutable::parse('2026-08-10 08:00:00 UTC');
        $firstSession = $this->makeSession($context, [
            'scheduled_start' => $start,
            'scheduled_end' => $start->addHour(),
        ]);
        $secondSession = $this->makeSession($context, [
            'staff_profile_id' => $context['teacherTwo']->id,
            'original_teacher_id' => $context['teacherTwo']->id,
            'scheduled_start' => $start->addHours(2),
            'scheduled_end' => $start->addHours(3),
        ]);
        $emptySession = $this->makeSession($context, [
            'scheduled_start' => $start->addHours(4),
            'scheduled_end' => $start->addHours(5),
        ]);
        $firstParticipant = $this->participant(
            $firstSession,
            $context['studentOne'],
            $context['enrollmentOne'],
            55,
        );
        $secondParticipant = $this->participant(
            $firstSession,
            $context['studentTwo'],
            $context['enrollmentTwo'],
            40,
        );
        $thirdParticipant = $this->participant(
            $secondSession,
            $context['studentTwo'],
            $context['enrollmentTwo'],
            30,
        );

        $otherContext = $this->context(Organization::factory()->create());
        $otherSession = $this->makeSession($otherContext, [
            'scheduled_start' => $start,
            'scheduled_end' => $start->addHour(),
        ]);
        $this->participant(
            $otherSession,
            $otherContext['studentOne'],
            $otherContext['enrollmentOne'],
        );

        DB::flushQueryLog();
        DB::enableQueryLog();
        $participants = app(SessionParticipantAdministrationQueries::class)->forSessions(
            (string) $organization->id,
            [$firstSession->id, $secondSession->id, $emptySession->id, $otherSession->id, $firstSession->id, ''],
        );
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        self::assertSame(
            [$firstSession->id, $secondSession->id, $emptySession->id, $otherSession->id],
            array_keys($participants),
        );
        self::assertEqualsCanonicalizing(
            [$firstParticipant->id, $secondParticipant->id],
            array_column($participants[$firstSession->id], 'id'),
        );
        self::assertSame([$thirdParticipant->id], array_column($participants[$secondSession->id], 'id'));
        self::assertSame([], $participants[$emptySession->id]);
        self::assertSame([], $participants[$otherSession->id]);
        self::assertSame(2, $queryCount, 'Bulk participant loading must remain constant and avoid N+1 queries.');
        self::assertSame([], app(SessionParticipantAdministrationQueries::class)->forSessions(
            (string) $organization->id,
            [],
        ));
    }

    /** @return ReportingContext */
    private function context(Organization $organization): array
    {
        $teacherUserOne = User::factory()->inOrganization((string) $organization->id)->create();
        $teacherUserTwo = User::factory()->inOrganization((string) $organization->id)->create();
        $studentUserOne = User::factory()->inOrganization((string) $organization->id)->create();
        $studentUserTwo = User::factory()->inOrganization((string) $organization->id)->create();
        $teacherOne = $this->teacher($organization, $teacherUserOne, 'ONE');
        $teacherTwo = $this->teacher($organization, $teacherUserTwo, 'TWO');
        $studentOne = StudentProfile::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $studentUserOne->id,
        ]);
        $studentTwo = StudentProfile::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $studentUserTwo->id,
        ]);
        $program = Program::factory()->create(['organization_id' => $organization->id]);
        $level = Level::factory()->create(['program_id' => $program->id]);
        $courseOne = Course::factory()->create([
            'organization_id' => $organization->id,
            'level_id' => $level->id,
            'session_mode' => SessionMode::Group,
        ]);
        $courseTwo = Course::factory()->create([
            'organization_id' => $organization->id,
            'level_id' => $level->id,
            'session_mode' => SessionMode::Group,
        ]);
        $groupOne = $this->group($organization, 'ONE');
        $groupTwo = $this->group($organization, 'TWO');
        $enrollmentOne = $this->enrollment($organization, $studentOne, $program, $level);
        $enrollmentTwo = $this->enrollment($organization, $studentTwo, $program, $level);

        return compact(
            'teacherOne', 'teacherTwo', 'studentOne', 'studentTwo',
            'courseOne', 'courseTwo', 'groupOne', 'groupTwo', 'enrollmentOne', 'enrollmentTwo',
        );
    }

    private function teacher(Organization $organization, User $user, string $suffix): StaffProfile
    {
        return StaffProfile::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'staff_code' => 'REPORT-'.$suffix.'-'.Str::upper(Str::random(8)),
            'employment_type' => EmploymentType::Contractor,
            'gender' => StaffGender::Male,
            'hired_at' => '2026-01-01',
        ]);
    }

    private function group(Organization $organization, string $suffix): Group
    {
        return Group::query()->create([
            'organization_id' => $organization->id,
            'code' => 'REPORT-'.$suffix.'-'.Str::upper(Str::random(8)),
            'name' => ['ar' => 'مجموعة التقرير', 'en' => 'Report group'],
            'capacity' => 20,
            'timezone' => 'UTC',
            'status' => GroupStatus::Active,
            'starts_on' => '2026-01-01',
        ]);
    }

    private function enrollment(
        Organization $organization,
        StudentProfile $student,
        Program $program,
        Level $level,
    ): Enrollment {
        return Enrollment::query()->create([
            'organization_id' => $organization->id,
            'student_profile_id' => $student->id,
            'program_id' => $program->id,
            'current_level_id' => $level->id,
            'status' => EnrollmentStatus::Active,
            'applied_at' => now('UTC')->subMonth(),
            'activated_at' => now('UTC')->subWeek(),
        ]);
    }

    /**
     * @param ReportingContext $context
     * @param array<string, mixed> $overrides
     */
    private function makeSession(array $context, array $overrides): Session
    {
        return Session::query()->create([
            'organization_id' => $context['teacherOne']->organization_id,
            'group_id' => $context['groupOne']->id,
            'course_id' => $context['courseOne']->id,
            'staff_profile_id' => $context['teacherOne']->id,
            'original_teacher_id' => $context['teacherOne']->id,
            'session_type' => 'regular',
            'status' => SessionStatus::Scheduled,
            'title' => ['ar' => 'حصة تقرير', 'en' => 'Report session'],
            ...$overrides,
        ]);
    }

    private function participant(
        Session $session,
        StudentProfile $student,
        Enrollment $enrollment,
        int $attendedMinutes = 0,
    ): SessionParticipant {
        return SessionParticipant::query()->create([
            'session_id' => $session->id,
            'student_profile_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'join_url_token' => Str::random(64),
            'invited_at' => now('UTC'),
            'attended_minutes' => $attendedMinutes,
        ]);
    }
}
