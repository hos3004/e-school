<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Sessions\Application\Actions\CompleteSessionAction;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Tests\TestCase;

/**
 * القرار المالي لحصة إضافية جزء من طلب إنشائها: إعفاء كامل من المستحقات،
 * أو سعر مخصّص يحل محل محلّل السعر التلقائي. كلاهما يُقرأ من عمودي
 * `payroll_exempt` و`payroll_rate_override_minor_units` على الحصة نفسها.
 */
final class ExtraSessionPayrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_payroll_exempt_session_never_records_an_entry_on_completion(): void
    {
        $context = $this->context(payrollExempt: true);

        app(CompleteSessionAction::class)->execute(
            Session::query()->findOrFail($context['session_id']),
            $context['user_id'],
            'اعتماد حصة إضافية بلا مقابل.',
        );

        self::assertSame(
            0,
            DB::table('payroll_entries')->where('session_id', $context['session_id'])->count(),
            'حصة معفاة من المستحقات لا يجوز أن تُنشئ لها أي قيدة.',
        );
    }

    public function test_a_custom_price_overrides_the_resolved_rate_on_completion(): void
    {
        $context = $this->context(payrollRateOverrideMinorUnits: 9_999);

        app(CompleteSessionAction::class)->execute(
            Session::query()->findOrFail($context['session_id']),
            $context['user_id'],
            'اعتماد حصة إضافية بسعر مخصّص.',
        );

        $entry = DB::table('payroll_entries')->where('session_id', $context['session_id'])->sole();

        self::assertSame(9_999, (int) $entry->amount, 'السعر المخصّص لم يحل محل محلّل السعر الافتراضي.');
        self::assertSame('manual_override', json_decode((string) $entry->description, true)['rate_id'] ?? null);
    }

    public function test_without_exemption_or_override_the_normal_catalog_price_still_applies(): void
    {
        $context = $this->context();

        app(CompleteSessionAction::class)->execute(
            Session::query()->findOrFail($context['session_id']),
            $context['user_id'],
            'اعتماد حصة إضافية بنفس السعر الافتراضي.',
        );

        $entry = DB::table('payroll_entries')->where('session_id', $context['session_id'])->sole();

        self::assertSame(2_500, (int) $entry->amount, 'الحصة الإضافية بلا إعفاء أو سعر مخصّص يجب أن تُسعَّر بكتالوج المؤسسة كأي حصة.');
    }

    /** @return array<string, string> */
    private function context(bool $payrollExempt = false, ?int $payrollRateOverrideMinorUnits = null): array
    {
        $organizationId = (string) Str::ulid();
        DB::table('organizations')->insert([
            'id' => $organizationId,
            'name' => json_encode(['ar' => 'مدرسة', 'en' => 'School'], JSON_THROW_ON_ERROR),
            'slug' => 'extra-session-'.strtolower((string) Str::ulid()),
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
            'staff_code' => 'EX-'.Str::upper(Str::random(8)), 'employment_type' => 'part_time',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $sessionStart = CarbonImmutable::now('UTC')->addDay();

        DB::table('teacher_contracts')->insert([
            'id' => (string) Str::ulid(), 'organization_id' => $organizationId,
            'staff_profile_id' => $staffProfileId, 'basis' => 'per_session',
            'effective_from' => $sessionStart->subMonth()->toDateString(), 'currency' => 'EGP',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('organization_settings')->insert([
            'id' => (string) Str::ulid(), 'organization_id' => $organizationId,
            'key' => (string) config('session_pay.setting_key'),
            'value' => json_encode([[
                'effective_from' => $sessionStart->subMonth()->toIso8601String(),
                'rates' => [
                    ['id' => (string) Str::ulid(), 'name' => 'فردي', 'session_type' => 'individual', 'duration_minutes' => 25, 'amount' => 2500, 'currency' => 'EGP'],
                ],
            ]], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $programId = (string) Str::ulid();
        DB::table('programs')->insert([
            'id' => $programId, 'organization_id' => $organizationId,
            'code' => 'EX-PROG-'.Str::upper(Str::random(6)),
            'name' => json_encode(['ar' => 'برنامج', 'en' => 'Program'], JSON_THROW_ON_ERROR),
            'default_session_minutes' => 25, 'currency' => 'EGP',
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
            'code' => 'EX-COURSE-'.Str::upper(Str::random(6)),
            'name' => json_encode(['ar' => 'دورة', 'en' => 'Course'], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $sessionId = (string) Str::ulid();
        DB::table('sessions')->insert([
            'id' => $sessionId, 'organization_id' => $organizationId, 'course_id' => $courseId,
            'staff_profile_id' => $staffProfileId, 'original_teacher_id' => $staffProfileId,
            'session_type' => 'individual',
            'title' => json_encode(['ar' => 'حصة إضافية', 'en' => 'Extra session'], JSON_THROW_ON_ERROR),
            'status' => SessionStatus::AwaitingReview->value,
            'scheduled_start' => $sessionStart,
            'scheduled_end' => $sessionStart->addMinutes(25),
            'payroll_exempt' => $payrollExempt,
            'payroll_rate_override_minor_units' => $payrollRateOverrideMinorUnits,
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
