<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Payroll\Application\Actions\SettleMakeupSessionAction;
use Modules\Payroll\Domain\Enums\PayrollEntryStatus;
use Modules\Sessions\Application\Actions\CancelSessionAction;
use Modules\Sessions\Application\Actions\CompleteSessionAction;
use Modules\Sessions\Domain\Contracts\SessionSchedulingGateway;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Events\SessionPostponed;
use Modules\Sessions\Domain\Models\Session;
use Tests\TestCase;

/**
 * تسوية الزوج: الحصة المؤجَّلة وحصتها التعويضية عمل واحد فأجره واحد.
 *
 * ثلاثة أخطاء متسلسلة كانت تجعل هذا مستحيلًا:
 *   1. `SessionPostponed` يُطلق من مسار الإدارة وحده، فتأجيل المعلم المعتمد
 *      يمضي بلا أي أثر مالي.
 *   2. القيدة المؤجَّلة تُعلَّق على معرّف الأصلية لا على تعويضيتها، فلا يجدها
 *      مسار التحرير أبدًا.
 *   3. `releases_deferred` في الإعدادات لا يقرأه أحد، فاعتماد التعويضية يُنشئ
 *      قيدة كاملة ثانية بينما تبقى الأولى قابلة للتحرير — دفعتان عن حصة واحدة.
 */
final class MakeupSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_postponing_defers_one_entry_pinned_to_the_makeup_session(): void
    {
        $context = $this->context();

        $makeupId = app(SessionSchedulingGateway::class)->scheduleMakeup(
            organizationId: $context['organization_id'],
            originalSessionId: $context['session_id'],
            startsAt: CarbonImmutable::now('UTC')->addDays(3),
            actorId: $context['user_id'],
            reason: 'ظرف طارئ للمعلم.',
        );

        $entries = DB::table('payroll_entries')->where('session_id', $context['session_id'])->get();

        self::assertCount(1, $entries, 'التأجيل لم يُنشئ قيدة مؤجَّلة واحدة.');
        $entry = $entries->first();
        self::assertSame(PayrollEntryStatus::Deferred->value, $entry->status);
        self::assertSame(2_500, (int) $entry->amount);
        self::assertSame(
            $makeupId,
            $entry->deferred_until_session_id,
            'القيدة مُعلَّقة على الأصلية لا على التعويضية، فلن يجدها مسار التحرير.',
        );
        self::assertSame(
            $context['user_id'],
            json_decode((string) $entry->rate_snapshot, true)['captured_by'],
            'القيدة لم تحمل منفّذ التأجيل.',
        );
    }

    public function test_the_administrative_route_fires_the_event_once(): void
    {
        $context = $this->context();
        Event::fake([SessionPostponed::class]);

        app(SessionSchedulingGateway::class)->scheduleMakeup(
            organizationId: $context['organization_id'],
            originalSessionId: $context['session_id'],
            startsAt: CarbonImmutable::now('UTC')->addDays(3),
            actorId: $context['user_id'],
            reason: 'تأجيل إداري.',
        );

        Event::assertDispatchedTimes(SessionPostponed::class, 1);
    }

    public function test_completing_the_makeup_releases_the_deferral_without_a_second_entry(): void
    {
        $context = $this->context();
        $makeupId = $this->postpone($context);

        $this->completeMakeup($context, $makeupId);

        $original = DB::table('payroll_entries')->where('session_id', $context['session_id'])->sole();
        self::assertSame(PayrollEntryStatus::Released->value, $original->status);
        self::assertSame(2_500, (int) $original->amount);

        self::assertSame(
            0,
            DB::table('payroll_entries')->where('session_id', $makeupId)->count(),
            'التعويضية أنشأت قيدة ثانية — المعلم يُدفع له مرتين عن حصة واحدة.',
        );
        self::assertSame(
            2_500,
            (int) DB::table('payroll_entries')
                ->where('staff_profile_id', $context['staff_profile_id'])
                ->where('status', '!=', PayrollEntryStatus::Deferred->value)
                ->sum('amount'),
            'إجمالي المستحق عن الزوج تجاوز أجر حصة واحدة.',
        );
    }

    public function test_a_group_original_keeps_its_own_rate(): void
    {
        $context = $this->context(sessionType: 'group', durationMinutes: 35);
        $makeupId = $this->postpone($context);

        $this->completeMakeup($context, $makeupId);

        $original = DB::table('payroll_entries')->where('session_id', $context['session_id'])->sole();
        self::assertSame(3_125, (int) $original->amount, 'الجماعي 35 دقيقة لم يُسعَّر بسعره.');
        self::assertSame(PayrollEntryStatus::Released->value, $original->status);
    }

    public function test_a_makeup_without_a_prior_deferral_records_one_entry_at_the_original_rate(): void
    {
        $context = $this->context();
        $makeupId = $this->makeupWithoutDeferral($context);

        $this->completeMakeup($context, $makeupId);

        $entry = DB::table('payroll_entries')->where('session_id', $makeupId)->sole();
        self::assertSame(
            2_500,
            (int) $entry->amount,
            'التعويضية سُعِّرت بنوعها `makeup` لا بنوع الأصلية، وهو ما لا يطابق أي سعر.',
        );
        self::assertSame(PayrollEntryStatus::Recorded->value, $entry->status);

        $description = json_decode((string) $entry->description, true);
        self::assertTrue($description['makeup_fallback']);
        self::assertSame($context['session_id'], $description['priced_from_session_id']);

        self::assertSame(
            0,
            DB::table('audit_log')->where('action', 'payroll.entry.rate_unresolved')->count(),
            'التعويضية أنتجت بند سعر غير محدَّد رغم أن لأصليتها سعرًا.',
        );
        self::assertSame(1, DB::table('audit_log')->where('action', 'payroll.entry.makeup_fallback')->count());
    }

    public function test_settling_twice_never_pays_twice(): void
    {
        $context = $this->context();
        $makeupId = $this->postpone($context);

        $this->completeMakeup($context, $makeupId);
        // اعتماد ثانٍ: زر الشاشة يسبق المجدول أو العكس.
        app(SettleMakeupSessionAction::class)->execute(
            organizationId: $context['organization_id'],
            makeupSessionId: $makeupId,
            staffProfileId: $context['staff_profile_id'],
            actorId: $context['user_id'],
            reason: 'إعادة تسوية.',
        );

        self::assertSame(
            1,
            DB::table('payroll_entries')->where('staff_profile_id', $context['staff_profile_id'])->count(),
        );
        self::assertSame(
            2_500,
            (int) DB::table('payroll_entries')->where('session_id', $context['session_id'])->value('amount'),
        );
    }

    public function test_a_teacher_cancelled_makeup_leaves_the_deferral_untouched(): void
    {
        $context = $this->context();
        $makeupId = $this->postpone($context);

        $makeup = Session::query()->findOrFail($makeupId);
        app(CancelSessionAction::class)->execute(
            $makeup,
            SessionStatus::CancelledByTeacher,
            'المعلم اعتذر عن التعويضية.',
            $context['user_id'],
        );

        self::assertSame(
            PayrollEntryStatus::Deferred->value,
            DB::table('payroll_entries')->where('session_id', $context['session_id'])->value('status'),
            'غياب المعلم عن التعويضية حرّر المستحق المؤجَّل.',
        );
    }

    public function test_the_backfill_command_is_a_dry_run_by_default_and_never_duplicates(): void
    {
        $context = $this->context();
        $makeupId = $this->makeupWithoutDeferral($context);
        DB::table('sessions')->where('id', $context['session_id'])->update(['status' => SessionStatus::Postponed->value]);

        $this->artisan('payroll:backfill-postponements')->assertExitCode(0);
        self::assertSame(0, DB::table('payroll_entries')->count(), 'العرض فقط كتب في الدفتر.');

        $this->artisan('payroll:backfill-postponements', ['--execute' => true])->assertExitCode(0);
        $entry = DB::table('payroll_entries')->where('session_id', $context['session_id'])->sole();
        self::assertSame(PayrollEntryStatus::Deferred->value, $entry->status);
        self::assertSame($makeupId, $entry->deferred_until_session_id);
        self::assertSame(1, DB::table('audit_log')->where('action', 'payroll.postponement_backfilled')->count());

        $this->artisan('payroll:backfill-postponements', ['--execute' => true])->assertExitCode(0);
        self::assertSame(1, DB::table('payroll_entries')->where('session_id', $context['session_id'])->count());
    }

    public function test_the_backfill_never_adds_a_second_entry_after_the_makeup_was_already_settled(): void
    {
        $context = $this->context();
        $makeupId = $this->makeupWithoutDeferral($context);
        DB::table('sessions')->where('id', $context['session_id'])->update(['status' => SessionStatus::Postponed->value]);

        // الترتيب الخطر: الاعتماد الآلي سبق المعالجة، فكتب قيدة fallback.
        $this->completeMakeup($context, $makeupId);
        self::assertSame(1, DB::table('payroll_entries')->count());

        $this->artisan('payroll:backfill-postponements', ['--execute' => true])->assertExitCode(0);

        self::assertSame(
            1,
            DB::table('payroll_entries')->count(),
            'المعالجة أضافت قيدة مؤجَّلة فوق قيدة fallback قائمة — دفعتان عن حصة واحدة.',
        );
        self::assertSame(
            2_500,
            (int) DB::table('payroll_entries')->sum('amount'),
        );
    }

    public function test_a_substitute_on_the_makeup_is_left_for_an_administrative_decision(): void
    {
        $context = $this->context();
        $makeupId = $this->postpone($context);

        // معلم آخر أدّى التعويضية.
        $substituteId = $this->substituteProfile($context);
        DB::table('sessions')->where('id', $makeupId)->update([
            'staff_profile_id' => $substituteId,
            'substitute_for_staff_id' => $context['staff_profile_id'],
        ]);

        $this->completeMakeup($context, $makeupId);

        self::assertSame(
            PayrollEntryStatus::Deferred->value,
            DB::table('payroll_entries')->where('session_id', $context['session_id'])->value('status'),
            'قيدة المعلم الأصلي حُرِّرت لأن البديل أدّى التعويضية.',
        );
        self::assertSame(
            0,
            DB::table('payroll_entries')->where('session_id', $makeupId)->count(),
            'أُنشئت قيدة كاملة للبديل بينما قيدة الأصلي ما زالت قابلة للتحرير — دفعتان لشخصين.',
        );
        self::assertSame(
            1,
            DB::table('audit_log')->where('action', 'payroll.makeup.manual_settlement_required')->count(),
        );
    }

    /** @param array<string, string> $context */
    private function substituteProfile(array $context): string
    {
        $userId = (string) Str::ulid();
        DB::table('users')->insert([
            'id' => $userId, 'organization_id' => $context['organization_id'], 'name' => 'معلم بديل',
            'email' => Str::lower(Str::random(10)).'@example.test',
            'password' => bcrypt('password-for-tests'),
            'locale' => 'ar', 'timezone' => 'UTC', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $profileId = (string) Str::ulid();
        DB::table('staff_profiles')->insert([
            'id' => $profileId, 'organization_id' => $context['organization_id'], 'user_id' => $userId,
            'staff_code' => 'SB-'.Str::upper(Str::random(8)), 'employment_type' => 'part_time',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('teacher_contracts')->insert([
            'id' => (string) Str::ulid(), 'organization_id' => $context['organization_id'],
            'staff_profile_id' => $profileId, 'basis' => 'per_session',
            'effective_from' => CarbonImmutable::now('UTC')->subMonth()->toDateString(), 'currency' => 'EGP',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $profileId;
    }

    /** @param array<string, string> $context */
    private function postpone(array $context): string
    {
        return app(SessionSchedulingGateway::class)->scheduleMakeup(
            organizationId: $context['organization_id'],
            originalSessionId: $context['session_id'],
            startsAt: CarbonImmutable::now('UTC')->addDays(3),
            actorId: $context['user_id'],
            reason: 'تأجيل للاختبار.',
        );
    }

    /**
     * تعويضية لأصلية مؤجَّلة قبل الإصلاح: لا قيدة مؤجَّلة لها إطلاقًا.
     *
     * @param array<string, string> $context
     */
    private function makeupWithoutDeferral(array $context): string
    {
        $makeupId = $this->postpone($context);
        DB::table('payroll_entries')->where('session_id', $context['session_id'])->delete();
        DB::table('payroll_periods')->delete();

        return $makeupId;
    }

    /** @param array<string, string> $context */
    private function completeMakeup(array $context, string $makeupId): void
    {
        DB::table('sessions')->where('id', $makeupId)->update(['status' => SessionStatus::AwaitingReview->value]);

        app(CompleteSessionAction::class)->execute(
            Session::query()->findOrFail($makeupId),
            $context['user_id'],
            'اعتماد الحصة التعويضية.',
        );
    }

    /** @return array<string, string> */
    private function context(string $sessionType = 'individual', int $durationMinutes = 25): array
    {
        $organizationId = (string) Str::ulid();
        DB::table('organizations')->insert([
            'id' => $organizationId,
            'name' => json_encode(['ar' => 'مدرسة', 'en' => 'School'], JSON_THROW_ON_ERROR),
            'slug' => 'makeup-'.strtolower((string) Str::ulid()),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $userId = (string) Str::ulid();
        DB::table('users')->insert([
            'id' => $userId, 'organization_id' => $organizationId, 'name' => 'معلم',
            'email' => Str::lower(Str::random(10)).'@example.test',
            'password' => bcrypt('password-for-tests'),
            'locale' => 'ar', 'timezone' => 'UTC', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $staffProfileId = (string) Str::ulid();
        DB::table('staff_profiles')->insert([
            'id' => $staffProfileId, 'organization_id' => $organizationId, 'user_id' => $userId,
            'staff_code' => 'MK-'.Str::upper(Str::random(8)), 'employment_type' => 'part_time',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $sessionStart = CarbonImmutable::now('UTC')->addDay();

        DB::table('teacher_contracts')->insert([
            'id' => (string) Str::ulid(), 'organization_id' => $organizationId,
            'staff_profile_id' => $staffProfileId, 'basis' => 'per_session',
            'effective_from' => $sessionStart->subMonth()->toDateString(), 'currency' => 'EGP',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        /*
         * لا سعر على العقد: التسعير يأتي من كتالوج المؤسسة بنوع الحصة ومدتها،
         * وهو بالضبط ما لا يطابقه نوع `makeup`.
         */
        DB::table('organization_settings')->insert([
            'id' => (string) Str::ulid(), 'organization_id' => $organizationId,
            'key' => (string) config('session_pay.setting_key'),
            'value' => json_encode([[
                'effective_from' => $sessionStart->subMonth()->toIso8601String(),
                'rates' => [
                    ['id' => (string) Str::ulid(), 'name' => 'فردي', 'session_type' => 'individual', 'duration_minutes' => 25, 'amount' => 2500, 'currency' => 'EGP'],
                    ['id' => (string) Str::ulid(), 'name' => 'جماعي', 'session_type' => 'group', 'duration_minutes' => 35, 'amount' => 3125, 'currency' => 'EGP'],
                ],
            ]], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $programId = (string) Str::ulid();
        DB::table('programs')->insert([
            'id' => $programId, 'organization_id' => $organizationId,
            'code' => 'MK-PROG-'.Str::upper(Str::random(6)),
            'name' => json_encode(['ar' => 'برنامج', 'en' => 'Program'], JSON_THROW_ON_ERROR),
            'default_session_minutes' => $durationMinutes, 'currency' => 'EGP',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $levelId = (string) Str::ulid();
        DB::table('levels')->insert([
            'id' => $levelId, 'program_id' => $programId, 'code' => 'L1',
            'name' => json_encode(['ar' => 'المستوى', 'en' => 'Level'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);

        $courseId = (string) Str::ulid();
        DB::table('courses')->insert([
            'id' => $courseId, 'organization_id' => $organizationId, 'level_id' => $levelId,
            'code' => 'MK-COURSE-'.Str::upper(Str::random(6)),
            'name' => json_encode(['ar' => 'دورة', 'en' => 'Course'], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $sessionId = (string) Str::ulid();
        DB::table('sessions')->insert([
            'id' => $sessionId, 'organization_id' => $organizationId, 'course_id' => $courseId,
            'staff_profile_id' => $staffProfileId, 'original_teacher_id' => $staffProfileId,
            'session_type' => $sessionType,
            'title' => json_encode(['ar' => 'حصة', 'en' => 'Session'], JSON_THROW_ON_ERROR),
            'status' => SessionStatus::Scheduled->value,
            'scheduled_start' => $sessionStart,
            'scheduled_end' => $sessionStart->addMinutes($durationMinutes),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'staff_profile_id' => $staffProfileId,
            'course_id' => $courseId,
            'session_id' => $sessionId,
            'program_id' => $programId,
        ];
    }
}
