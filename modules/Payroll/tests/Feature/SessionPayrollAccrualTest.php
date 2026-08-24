<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Payroll\Domain\Enums\PayrollEntryStatus;
use Modules\Payroll\Domain\Enums\PayrollPeriodStatus;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Events\SessionCancelled;
use Modules\Sessions\Domain\Events\SessionCompleted;
use Tests\TestCase;

/**
 * ADR-017 — احتساب أجر المعلم من دورة حياة الحصة.
 *
 * البنية المالية كانت قائمة بالكامل ولا يستدعيها شيء: الحصص تُقفل ولا تُنشأ
 * قيدة. هذه الاختبارات تحرس الرابط الجديد، وتحرس معه عقد الدفتر في
 * `CLAUDE.md` §4: القيدة بالسعر **وقت الحصة**، وتغيير السعر لاحقًا لا يمسّها.
 */
final class SessionPayrollAccrualTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_a_session_records_an_earning_at_the_rate_of_that_session(): void
    {
        $context = $this->payrollContext(ratePiastres: 5_000);

        Event::dispatch(new SessionCompleted(
            sessionId: $context['session_id'],
            organizationId: $context['organization_id'],
            courseId: $context['course_id'],
            staffProfileId: $context['staff_profile_id'],
            attendedMinutes: 60,
        ));

        $entry = DB::table('payroll_entries')
            ->where('session_id', $context['session_id'])
            ->first();

        $this->assertNotNull($entry, 'إقفال الحصة لم يُنشئ قيدة في الدفتر.');
        $this->assertSame('session_earning', $entry->entry_type);
        $this->assertSame('completed', $entry->outcome_key);
        $this->assertSame(5_000, (int) $entry->amount);
        $this->assertSame(PayrollEntryStatus::Recorded->value, $entry->status);
        $this->assertSame($context['staff_profile_id'], $entry->staff_profile_id);
        $this->assertSame($context['contract_id'], $entry->teacher_contract_id);

        $snapshot = json_decode((string) $entry->rate_snapshot, true);
        $this->assertIsArray($snapshot);
        $this->assertSame(5_000, $snapshot['amount_minor_units']);
    }

    public function test_raising_the_rate_afterwards_never_touches_an_existing_entry(): void
    {
        $context = $this->payrollContext(ratePiastres: 5_000);

        Event::dispatch(new SessionCompleted(
            sessionId: $context['session_id'],
            organizationId: $context['organization_id'],
            courseId: $context['course_id'],
            staffProfileId: $context['staff_profile_id'],
            attendedMinutes: 60,
        ));

        // المدرسة ترفع سعر المعلم بعد اعتماد الحصة.
        DB::table('teacher_rates')
            ->where('teacher_contract_id', $context['contract_id'])
            ->update(['amount' => 9_000]);

        $entry = DB::table('payroll_entries')
            ->where('session_id', $context['session_id'])
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(
            5_000,
            (int) $entry->amount,
            'القيدة القديمة تأثرت بتغيير السعر — الدفتر لم يعد append-only.',
        );
    }

    public function test_the_same_event_arriving_twice_does_not_duplicate_the_entry(): void
    {
        $context = $this->payrollContext(ratePiastres: 4_000);

        $event = new SessionCompleted(
            sessionId: $context['session_id'],
            organizationId: $context['organization_id'],
            courseId: $context['course_id'],
            staffProfileId: $context['staff_profile_id'],
            attendedMinutes: 60,
        );

        Event::dispatch($event);
        Event::dispatch($event);

        $this->assertSame(
            1,
            DB::table('payroll_entries')->where('session_id', $context['session_id'])->count(),
        );
    }

    public function test_teacher_cancellation_records_a_negative_deduction(): void
    {
        $context = $this->payrollContext(
            ratePiastres: 5_000,
            sessionStatus: SessionStatus::CancelledByTeacher,
        );

        Event::dispatch(new SessionCancelled(
            sessionId: $context['session_id'],
            organizationId: $context['organization_id'],
            courseId: $context['course_id'],
            staffProfileId: $context['staff_profile_id'],
            cancelledAs: SessionStatus::CancelledByTeacher,
            cancelledAt: CarbonImmutable::now('UTC')->toIso8601String(),
            cancelledById: null,
            reason: 'ظرف طارئ',
        ));

        $entry = DB::table('payroll_entries')
            ->where('session_id', $context['session_id'])
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame('session_deduction', $entry->entry_type);
        $this->assertSame('teacher_absent', $entry->outcome_key);
        $this->assertSame(-5_000, (int) $entry->amount);
    }

    public function test_a_school_cancellation_costs_the_teacher_nothing(): void
    {
        $context = $this->payrollContext(
            ratePiastres: 5_000,
            sessionStatus: SessionStatus::CancelledBySchool,
        );

        Event::dispatch(new SessionCancelled(
            sessionId: $context['session_id'],
            organizationId: $context['organization_id'],
            courseId: $context['course_id'],
            staffProfileId: $context['staff_profile_id'],
            cancelledAs: SessionStatus::CancelledBySchool,
            cancelledAt: CarbonImmutable::now('UTC')->toIso8601String(),
            cancelledById: null,
            reason: 'عطلة رسمية',
        ));

        $this->assertSame(
            0,
            DB::table('payroll_entries')->where('session_id', $context['session_id'])->count(),
            'إلغاء المؤسسة لا يجوز أن يُنشئ أثرًا ماليًا على المعلم.',
        );
    }

    public function test_a_teacher_without_a_rate_gets_no_invented_zero_entry(): void
    {
        $context = $this->payrollContext(ratePiastres: null);

        Event::dispatch(new SessionCompleted(
            sessionId: $context['session_id'],
            organizationId: $context['organization_id'],
            courseId: $context['course_id'],
            staffProfileId: $context['staff_profile_id'],
            attendedMinutes: 60,
        ));

        $this->assertSame(
            0,
            DB::table('payroll_entries')->count(),
            'قيدة بصفر تخفي نقص العقد خلف رقم يبدو صحيحًا.',
        );
    }

    public function test_a_locked_period_rejects_the_entry_without_breaking_the_session(): void
    {
        $context = $this->payrollContext(ratePiastres: 5_000);

        $sessionStart = CarbonImmutable::parse((string) DB::table('sessions')
            ->where('id', $context['session_id'])
            ->value('scheduled_start'))->utc();

        DB::table('payroll_periods')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $context['organization_id'],
            'year' => (int) $sessionStart->year,
            'month' => (int) $sessionStart->month,
            'starts_on' => $sessionStart->startOfMonth()->toDateString(),
            'ends_on' => $sessionStart->endOfMonth()->toDateString(),
            'status' => PayrollPeriodStatus::Locked->value,
            'totals' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Event::dispatch(new SessionCompleted(
            sessionId: $context['session_id'],
            organizationId: $context['organization_id'],
            courseId: $context['course_id'],
            staffProfileId: $context['staff_profile_id'],
            attendedMinutes: 60,
        ));

        $this->assertSame(0, DB::table('payroll_entries')->count());
    }

    /**
     * مؤسسة · معلم بعقد per_session وسعر افتراضي · برنامج ومستوى ودورة · حصة.
     *
     * @return array<string, string>
     */
    private function payrollContext(
        ?int $ratePiastres,
        SessionStatus $sessionStatus = SessionStatus::Completed,
    ): array {
        $organizationId = (string) Str::ulid();
        DB::table('organizations')->insert([
            'id' => $organizationId,
            'name' => json_encode(['ar' => 'مدرسة', 'en' => 'School'], JSON_THROW_ON_ERROR),
            'slug' => 'payroll-'.strtolower((string) Str::ulid()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = (string) Str::ulid();
        DB::table('users')->insert([
            'id' => $userId,
            'organization_id' => $organizationId,
            'name' => 'معلم',
            'email' => Str::lower(Str::random(10)).'@example.test',
            'password' => bcrypt('password-for-tests'),
            'locale' => 'ar',
            'timezone' => 'UTC',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $staffProfileId = (string) Str::ulid();
        DB::table('staff_profiles')->insert([
            'id' => $staffProfileId,
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'staff_code' => 'PR-'.Str::upper(Str::random(8)),
            'employment_type' => 'part_time',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sessionStart = CarbonImmutable::now('UTC')->subDays(2);

        $contractId = (string) Str::ulid();
        DB::table('teacher_contracts')->insert([
            'id' => $contractId,
            'organization_id' => $organizationId,
            'staff_profile_id' => $staffProfileId,
            'basis' => 'per_session',
            'effective_from' => $sessionStart->subMonth()->toDateString(),
            'currency' => 'EGP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($ratePiastres !== null) {
            DB::table('teacher_rates')->insert([
                'id' => (string) Str::ulid(),
                'teacher_contract_id' => $contractId,
                'scope' => 'default',
                'amount' => $ratePiastres,
                'currency' => 'EGP',
                'effective_from' => $sessionStart->subMonth()->toDateString(),
                'created_at' => now(),
            ]);
        }

        $programId = (string) Str::ulid();
        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organizationId,
            'code' => 'PR-PROG-'.Str::upper(Str::random(6)),
            'name' => json_encode(['ar' => 'برنامج', 'en' => 'Program'], JSON_THROW_ON_ERROR),
            'default_session_minutes' => 60,
            'currency' => 'EGP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $levelId = (string) Str::ulid();
        DB::table('levels')->insert([
            'id' => $levelId,
            'program_id' => $programId,
            'code' => 'L1',
            'name' => json_encode(['ar' => 'المستوى', 'en' => 'Level'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);

        $courseId = (string) Str::ulid();
        DB::table('courses')->insert([
            'id' => $courseId,
            'organization_id' => $organizationId,
            'level_id' => $levelId,
            'code' => 'PR-COURSE-'.Str::upper(Str::random(6)),
            'name' => json_encode(['ar' => 'دورة', 'en' => 'Course'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sessionId = (string) Str::ulid();
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'organization_id' => $organizationId,
            'course_id' => $courseId,
            'staff_profile_id' => $staffProfileId,
            'original_teacher_id' => $staffProfileId,
            'session_type' => 'group',
            'title' => json_encode(['ar' => 'حصة', 'en' => 'Session'], JSON_THROW_ON_ERROR),
            'status' => $sessionStatus->value,
            'scheduled_start' => $sessionStart,
            'scheduled_end' => $sessionStart->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'organization_id' => $organizationId,
            'staff_profile_id' => $staffProfileId,
            'contract_id' => $contractId,
            'course_id' => $courseId,
            'session_id' => $sessionId,
        ];
    }
}
