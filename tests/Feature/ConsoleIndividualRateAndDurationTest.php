<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Identity\Domain\Models\User;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Staff\Domain\Enums\ContractBasis;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\RateScope;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Models\TeacherAvailability;
use Modules\Staff\Domain\Models\TeacherContract;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Testing\Fixtures;
use Tests\TestCase;

/**
 * مدة الحصة الفردية المخصّصة وسعر حصة المعلم من الكونسول.
 *
 * المحروس: المدة لم تعد محصورة في مدد المؤسسة المسعّرة، ومع ذلك لا تُقبل مدة
 * بلا سعر يقابلها — لأن الحصة حينها تُقفل بلا قيدة مستحق. والسعر صار قابلًا
 * للتغيير دائمًا من ملف المعلم: الجديد يبدأ من تاريخه ويُقفل السابق عنده،
 * فلا يُمس سعر حصة ماضية.
 */
final class ConsoleIndividualRateAndDurationTest extends TestCase
{
    use RefreshDatabase;

    private const CUSTOM_DURATION = 37;

    /** @var list<string> */
    private array $permissions = [
        'admin.panel.access', 'student.view.any', 'staff.view.any',
        'schedule.manage', 'schedule.view', 'enrollment.create', 'enrollment.view',
        'staff.contract.view', 'staff.contract.update',
    ];

    private string $organizationId;

    private string $courseId;

    private string $programId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['console.enabled' => true]);
        $this->withoutVite();
        foreach ($this->permissions as $ability) {
            Gate::define($ability, fn (): bool => in_array($ability, $this->permissions, true));
        }
        $this->organizationId = Fixtures::organizationId();
        $this->courseId = Fixtures::courseId();
        DB::table('courses')->where('id', $this->courseId)
            ->update(['session_mode' => SessionMode::Individual->value]);
        $this->programId = (string) DB::table('courses')
            ->join('levels', 'courses.level_id', '=', 'levels.id')
            ->where('courses.id', $this->courseId)
            ->value('levels.program_id');
    }

    public function test_a_priced_duration_still_needs_no_teacher_rate(): void
    {
        $student = $this->student();
        $teacher = $this->teacher();
        $priced = (int) (config('scheduling.individual_session_durations')[0] ?? 25);

        $this->actingAs($this->admin(), 'web')
            ->post('/manage/students/'.$student->id.'/teacher', $this->assignment($teacher, $priced))
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame($priced, (int) Schedule::query()
            ->where('student_profile_id', (string) $student->id)->value('duration_minutes'));
        $this->assertDatabaseCount('teacher_rates', 0);
    }

    public function test_a_custom_duration_is_refused_while_the_teacher_has_no_rate(): void
    {
        $student = $this->student();
        $teacher = $this->teacher();

        $this->actingAs($this->admin(), 'web')
            ->post('/manage/students/'.$student->id.'/teacher', $this->assignment($teacher))
            ->assertSessionHasErrors('business_rule');

        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_a_custom_duration_is_accepted_once_a_course_rate_is_recorded(): void
    {
        $student = $this->student();
        $teacher = $this->teacher();
        $admin = $this->admin();

        $this->actingAs($admin, 'web')
            ->post('/manage/teachers/'.$teacher.'/rates', [
                'scope' => RateScope::Course->value,
                'course_id' => $this->courseId,
                'amount_major' => '45.50',
                'effective_from' => CarbonImmutable::now('UTC')->toDateString(),
                'reason' => 'سعر حصة القرآن الفردي المتفق عليه مع المعلم',
            ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('teacher_rates', [
            'scope' => RateScope::Course->value,
            'course_id' => $this->courseId,
            'program_id' => $this->programId,
            'amount' => 4550,
            'effective_to' => null,
        ]);

        $this->post('/manage/students/'.$student->id.'/teacher', $this->assignment($teacher))
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(self::CUSTOM_DURATION, (int) Schedule::query()
            ->where('student_profile_id', (string) $student->id)->value('duration_minutes'));
    }

    public function test_a_duration_outside_the_configured_limits_is_refused(): void
    {
        $student = $this->student();
        $teacher = $this->teacher();

        $this->actingAs($this->admin(), 'web')
            ->post(
                '/manage/students/'.$student->id.'/teacher',
                $this->assignment($teacher, (int) config('session_pay.min_duration') - 1),
            )->assertSessionHasErrors('duration_minutes');

        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_a_new_rate_closes_the_previous_one_at_its_effective_date(): void
    {
        $teacher = $this->teacher();
        $admin = $this->admin();
        $url = '/manage/teachers/'.$teacher.'/rates';
        $firstFrom = CarbonImmutable::now('UTC')->subMonth()->toDateString();
        $secondFrom = CarbonImmutable::now('UTC')->toDateString();

        $this->actingAs($admin, 'web')->post($url, [
            'scope' => RateScope::Default->value,
            'amount_major' => '30.00',
            'effective_from' => $firstFrom,
            'reason' => 'السعر الأول عند بداية التعاقد',
        ])->assertSessionHasNoErrors();

        $this->post($url, [
            'scope' => RateScope::Default->value,
            'amount_major' => '40.00',
            'effective_from' => $secondFrom,
            'reason' => 'رفع سعر الحصة من بداية هذا الشهر',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, DB::table('teacher_rates')->count());
        $this->assertSame($secondFrom, CarbonImmutable::parse((string) DB::table('teacher_rates')
            ->where('amount', 3000)->value('effective_to'))->toDateString());
        $this->assertNull(DB::table('teacher_rates')->where('amount', 4000)->value('effective_to'));
        $this->assertTrue(DB::table('audit_log')->where('action', 'staff.rate_superseded')->exists());
    }

    public function test_a_rate_starting_before_the_current_one_is_refused(): void
    {
        $teacher = $this->teacher();
        $url = '/manage/teachers/'.$teacher.'/rates';

        $this->actingAs($this->admin(), 'web')->post($url, [
            'scope' => RateScope::Default->value,
            'amount_major' => '30.00',
            'effective_from' => CarbonImmutable::now('UTC')->toDateString(),
            'reason' => 'السعر الساري الحالي',
        ])->assertSessionHasNoErrors();

        $this->post($url, [
            'scope' => RateScope::Default->value,
            'amount_major' => '40.00',
            'effective_from' => CarbonImmutable::now('UTC')->subWeek()->toDateString(),
            'reason' => 'محاولة تسعير بأثر رجعي',
        ])->assertSessionHasErrors('amount_major');

        $this->assertSame(1, DB::table('teacher_rates')->count());
    }

    public function test_rates_are_closed_without_the_contract_permissions(): void
    {
        $teacher = $this->teacher();
        $admin = $this->admin();
        $this->permissions = ['admin.panel.access', 'staff.view.any'];

        $this->actingAs($admin, 'web')->get('/manage/teachers/'.$teacher)->assertOk()
            ->assertInertia(fn ($page) => $page->where('rates', null));

        $this->post('/manage/teachers/'.$teacher.'/rates', [
            'scope' => RateScope::Default->value,
            'amount_major' => '40.00',
            'effective_from' => CarbonImmutable::now('UTC')->toDateString(),
            'reason' => 'بلا صلاحية',
        ])->assertForbidden();

        $this->assertDatabaseCount('teacher_rates', 0);
    }

    public function test_the_assignment_form_carries_each_teacher_rate(): void
    {
        $student = $this->student();
        $teacher = $this->teacher();

        $this->actingAs($this->admin(), 'web')
            ->getJson('/manage/students/'.$student->id.'/teacher-options?course_id='.$this->courseId)
            ->assertOk()
            ->assertJsonPath('teachers.0.value', $teacher)
            ->assertJsonPath('teachers.0.rate_major', null)
            ->assertJsonPath('teachers.0.requires_rate', true);

        $this->post('/manage/teachers/'.$teacher.'/rates', [
            'scope' => RateScope::Course->value,
            'course_id' => $this->courseId,
            'amount_major' => '45.50',
            'effective_from' => CarbonImmutable::now('UTC')->toDateString(),
            'reason' => 'سعر حصة القرآن الفردي',
        ])->assertSessionHasNoErrors();

        $this->getJson('/manage/students/'.$student->id.'/teacher-options?course_id='.$this->courseId)
            ->assertOk()->assertJsonPath('teachers.0.rate_major', '45.50');
    }

    public function test_the_rate_sent_with_the_assignment_prices_the_custom_duration(): void
    {
        $student = $this->student();
        $teacher = $this->teacher();

        $this->actingAs($this->admin(), 'web')->post(
            '/manage/students/'.$student->id.'/teacher',
            [...$this->assignment($teacher), 'session_rate_major' => '52.00'],
        )->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(self::CUSTOM_DURATION, (int) Schedule::query()
            ->where('student_profile_id', (string) $student->id)->value('duration_minutes'));
        $this->assertDatabaseHas('teacher_rates', [
            'scope' => RateScope::Course->value,
            'course_id' => $this->courseId,
            'amount' => 5200,
            'effective_to' => null,
        ]);
    }

    /** الحقل يصل معبّأً بالسعر الساري، فإعادة إرساله كما هو لا تنشئ سطرًا جديدًا. */
    public function test_resending_the_same_rate_records_nothing_new(): void
    {
        $student = $this->student();
        $teacher = $this->teacher();
        $admin = $this->admin();

        $this->actingAs($admin, 'web')->post('/manage/teachers/'.$teacher.'/rates', [
            'scope' => RateScope::Course->value,
            'course_id' => $this->courseId,
            'amount_major' => '45.50',
            'effective_from' => CarbonImmutable::now('UTC')->toDateString(),
            'reason' => 'السعر المتفق عليه',
        ])->assertSessionHasNoErrors();

        $this->post(
            '/manage/students/'.$student->id.'/teacher',
            [...$this->assignment($teacher), 'session_rate_major' => '45.50'],
        )->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1, DB::table('teacher_rates')->count());
    }

    /** @return array<string, mixed> */
    private function assignment(string $teacher, int $duration = self::CUSTOM_DURATION): array
    {
        return [
            'course_id' => $this->courseId,
            'staff_profile_id' => $teacher,
            'weekly_slots' => [['weekday' => 1, 'start_time' => '10:00']],
            'duration_minutes' => $duration,
            'interval_weeks' => 1,
            'timezone' => 'UTC',
            'starts_on' => CarbonImmutable::now('UTC')->toDateString(),
            'reason' => 'إسناد معلم القرآن الفردي بمدة متفق عليها',
        ];
    }

    private function admin(): User
    {
        return User::factory()->inOrganization($this->organizationId)->create();
    }

    private function student(): StudentProfile
    {
        $profile = StudentProfile::query()->findOrFail(Fixtures::studentProfileId());
        Enrollment::query()->create([
            'organization_id' => $this->organizationId,
            'student_profile_id' => (string) $profile->id,
            'program_id' => $this->programId,
            'status' => EnrollmentStatus::Active,
            'applied_at' => now()->subMonth()->utc(),
            'activated_at' => now()->subMonth()->utc(),
        ]);

        return $profile;
    }

    /** معلم مؤهل ومتاح طوال الأسبوع بعقد أجر بالحصة — حالة معلمي الإنتاج. */
    private function teacher(): string
    {
        $staffProfileId = Fixtures::staffProfileId();
        // Fixtures يكتب employment_type بقيمة ContractBasis لا يقبلها الـEnum،
        // وصفحة الملف تقرأ الخاصية فعلًا.
        DB::table('staff_profiles')->where('id', $staffProfileId)
            ->update(['employment_type' => EmploymentType::Contractor->value]);
        Fixtures::qualifyTeacher($staffProfileId, $this->courseId);

        TeacherContract::query()->create([
            'organization_id' => $this->organizationId,
            'staff_profile_id' => $staffProfileId,
            'basis' => ContractBasis::PerSession,
            'effective_from' => CarbonImmutable::now('UTC')->subMonths(2)->toDateString(),
            'currency' => 'EGP',
        ]);

        for ($weekday = 0; $weekday <= 6; $weekday++) {
            TeacherAvailability::query()->create([
                'staff_profile_id' => $staffProfileId,
                'weekday' => $weekday,
                'start_time' => '00:00:00',
                'end_time' => '23:59:59',
                'timezone' => 'UTC',
                'effective_from' => CarbonImmutable::now('UTC')->subMonths(2)->startOfDay(),
                'approval_status' => TeacherAvailabilityApprovalStatus::Approved,
                'approved_at' => CarbonImmutable::now('UTC')->subMonths(2),
            ]);
        }

        return $staffProfileId;
    }
}
