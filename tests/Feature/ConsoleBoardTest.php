<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Academics\Domain\Models\Course;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Payroll\Application\Actions\RecordPayrollEntryAction;
use Modules\Payroll\Domain\Enums\PayrollPeriodStatus;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Modules\Sessions\Domain\Contracts\SessionClosureBacklogQuery;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Shared\ValueObjects\Money;
use Shared\ValueObjects\TimeRange;
use Tests\TestCase;

/**
 * اللوحة تقوم على قاعدتين: لكل رقم مصدر واحد، والمبلغ لا يخرج بلا مقامه.
 */
final class ConsoleBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['console.enabled' => true, 'features.payroll' => true]);
        $this->travelTo(CarbonImmutable::create(2026, 9, 20, 9, 0, 0, 'UTC'));
        $this->seed(AccessControlSeeder::class);
        app(PermissionGateRegistrar::class)->register();
    }

    public function test_permissions_and_organization_scope_guard_the_board(): void
    {
        [$org] = $this->context();
        $this->get('/manage/board')->assertRedirect(route('login'));
        $this->actingAs($this->actor($org, ['admin.panel.access', 'session.view']))->get('/manage/board')->assertForbidden();
        $this->actingAs($this->admin($org))->get('/manage/board')->assertOk();
    }

    public function test_the_backlog_excludes_fresh_and_superseded_and_splits_by_whether_the_room_opened(): void
    {
        [$org, $staff, $course] = $this->context();
        [$staffB] = $this->teacher($org);

        $opened = $this->lesson($org, $staff, $course, -600, -540);
        $this->room($opened, started: true);
        $this->lesson($org, $staffB, $course, -600, -540);

        // انتهت قبل دقائق: داخل المهلة فلا تُعد متروكة.
        $this->lesson($org, $staff, $course, -70, -10);
        // مستبدَلة: أثر إعادة جدولة لا حصة.
        $this->lesson($org, $staff, $course, -900, -840, SessionStatus::Superseded);
        // منتهية بقرار: خرجت من الطابور.
        $this->lesson($org, $staff, $course, -900, -840, SessionStatus::Completed);

        $this->actingAs($this->admin($org))->get('/manage/board')->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('Console/Board')
                ->where('backlog.total', 2)
                ->where('backlog.started', 1)
                ->where('backlog.neverStarted', 1)
                ->has('backlog.teachers', 2));
    }

    public function test_awaiting_review_is_counted_apart_from_the_backlog(): void
    {
        [$org, $staff, $course] = $this->context();
        [$staffB] = $this->teacher($org);

        $this->lesson($org, $staff, $course, -600, -540);
        $this->lesson($org, $staffB, $course, -600, -540, SessionStatus::AwaitingReview);

        /*
         * ضمّ العددين في عدّاد واحد هو منشأ التضارب الذي أعطى أربعة أرقام
         * لسؤال واحد، فالفصل مثبَّت باختبار لا بتعليق.
         */
        $this->actingAs($this->admin($org))->get('/manage/board')
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('backlog.total', 1)
                ->where('awaiting.total', 1));
    }

    public function test_the_amount_never_appears_without_the_sessions_missing_from_the_ledger(): void
    {
        [$org, $staff, $course, $contract, $period] = $this->context();
        $paid = $this->lesson($org, $staff, $course, -1500, -1440, SessionStatus::Completed);
        app(RecordPayrollEntryAction::class)->execute($org, $period->id, $staff, $contract, 'session_earning', 'completed',
            Money::of(5000, 'EGP'),
            new TimeRange(CarbonImmutable::parse($paid->scheduled_start), CarbonImmutable::parse($paid->scheduled_end)),
            sessionId: $paid->id, resolvedVia: 'default');

        [$staffB] = $this->teacher($org);
        $this->lesson($org, $staffB, $course, -600, -540);

        $this->actingAs($this->admin($org))->get('/manage/board')
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('ledger.entries', 1)
                // المقام = الطابور + المنتظِرة، من المصدر نفسه لا من استعلام ثانٍ.
                ->where('ledger.sessionsWithoutEntry', 1)
                ->where('backlog.total', 1));
    }

    public function test_a_quiet_school_shows_no_cards_at_all(): void
    {
        [$org, $staff, $course] = $this->context();
        $this->lesson($org, $staff, $course, 120, 180);

        $this->actingAs($this->admin($org))->get('/manage/board')
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('backlog.total', 0)->where('awaiting.total', 0)
                // لا قيود ولا فجوة: البطاقة نفسها لا تُعرض، لا تُعرض بصفر.
                ->where('ledger', null)
                ->where('today.count', 1));
    }

    public function test_the_contract_is_the_single_definition_and_ignores_other_organizations(): void
    {
        [$org, $staff, $course] = $this->context();
        [$otherOrg, $otherStaff, $otherCourse] = $this->context();
        $this->lesson($org, $staff, $course, -600, -540);
        $this->lesson($otherOrg, $otherStaff, $otherCourse, -600, -540);

        $closure = app(SessionClosureBacklogQuery::class);
        $cutoff = $closure->cutoff();
        $this->assertSame(1, $closure->backlog($org, $cutoff)->total);
        $this->assertCount(1, $closure->backlogSessionIds($org, $cutoff));
        $this->assertSame(1, array_sum($closure->backlog($org, $cutoff)->byTeacher));
    }

    public function test_sessions_with_no_report_row_at_all_are_counted_as_missing(): void
    {
        [$org, $staff, $course] = $this->context();
        [$staffB] = $this->teacher($org);
        $this->lesson($org, $staff, $course, -600, -540, SessionStatus::AwaitingReview);
        $this->lesson($org, $staffB, $course, -600, -540, SessionStatus::AwaitingReview);

        /*
         * `forSessions` تعيد الحصص التي لها صف تقرير فقط. المرور على المُعاد
         * وحده كان يقرأ صفرًا في اللحظة التي تكون كلها فيها بلا تقرير — أي أن
         * العدّاد الوحيد الذي يقول «هذه عالقة» كان يطمئن كذبًا.
         */
        $this->actingAs($this->admin($org))->get('/manage/board')
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('awaiting.total', 2)
                ->where('awaiting.missingReport', 2));
    }

    public function test_a_past_final_session_with_no_entry_still_counts_against_the_ledger(): void
    {
        [$org, $staff, $course] = $this->context();

        /*
         * حصة غياب ماضية بلا قيدة لا تعود إلى الطابور ولا إلى المراجعة أبدًا.
         * قياس المقام بالطابور وحده كان يبلغ صفرًا يوم تُصفّى الحصص المعلّقة
         * بينما مالٌ حقيقي خارج الدفتر.
         */
        $this->lesson($org, $staff, $course, -1500, -1440, SessionStatus::NoShow);

        $this->actingAs($this->admin($org))->get('/manage/board')
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('backlog.total', 0)
                ->where('awaiting.total', 0)
                ->where('ledger.sessionsWithoutEntry', 1));
    }

    public function test_teachers_are_ordered_by_amount_not_by_its_text(): void
    {
        [$org, $staff, $course, $contract, $period] = $this->context();
        [$staffB, , $contractB] = $this->teacherWithContract($org);

        $big = $this->lesson($org, $staff, $course, -3000, -2940, SessionStatus::Completed);
        $small = $this->lesson($org, $staffB, $course, -3000, -2940, SessionStatus::Completed);
        $this->entry($org, $staff, $contract, $period, $big, 47500);
        $this->entry($org, $staffB, $contractB, $period, $small, 7500);

        // نصًّا "75.00" أكبر من "475.00"، فالترتيب الخاطئ يضع الأصغر أولًا.
        $this->actingAs($this->admin($org))->get('/manage/board')
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('ledger.teachers.0.amount', '475.00')
                ->where('ledger.teachers.1.amount', '75.00'));
    }

    public function test_today_counts_the_school_day_and_ignores_cancelled_and_superseded(): void
    {
        [$org, $staff, $course] = $this->context();
        [$staffB] = $this->teacher($org);
        [$staffC] = $this->teacher($org);
        $this->lesson($org, $staff, $course, 120, 180);
        $this->lesson($org, $staffB, $course, 200, 260, SessionStatus::CancelledByStudent);
        $this->lesson($org, $staffC, $course, 300, 360, SessionStatus::Superseded);

        $this->actingAs($this->admin($org))->get('/manage/board')
            ->assertInertia(fn (Assert $page): Assert => $page->where('today.count', 1));
    }

    /** @return array{string, string, Course, string, PayrollPeriod} */
    private function context(): array
    {
        $org = (string) Organization::factory()->create()->id;
        [$staff] = $this->teacher($org);
        $contract = (string) Str::ulid();
        DB::table('teacher_contracts')->insert(['id' => $contract, 'organization_id' => $org, 'staff_profile_id' => $staff,
            'basis' => 'per_session', 'currency' => 'EGP', 'effective_from' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
        $course = Course::factory()->create(['organization_id' => $org, 'name' => ['ar' => 'دورة القرآن']]);
        $date = CarbonImmutable::create(2026, 9, 1, 0, 0, 0, 'UTC');
        $period = PayrollPeriod::query()->create(['organization_id' => $org, 'year' => 2026, 'month' => 9,
            'starts_on' => $date, 'ends_on' => $date->endOfMonth(), 'status' => PayrollPeriodStatus::Open, 'totals' => []]);

        return [$org, $staff, $course, $contract, $period];
    }

    /** @return array{string, User, string} */
    private function teacherWithContract(string $org): array
    {
        [$staff, $user] = $this->teacher($org);
        $contract = (string) Str::ulid();
        DB::table('teacher_contracts')->insert(['id' => $contract, 'organization_id' => $org,
            'staff_profile_id' => $staff, 'basis' => 'per_session', 'currency' => 'EGP',
            'effective_from' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);

        return [$staff, $user, $contract];
    }

    private function entry(string $org, string $staff, string $contract, PayrollPeriod $period,
        Session $session, int $amount): void
    {
        app(RecordPayrollEntryAction::class)->execute($org, $period->id, $staff, $contract,
            'session_earning', 'completed', Money::of($amount, 'EGP'),
            new TimeRange(CarbonImmutable::parse($session->scheduled_start), CarbonImmutable::parse($session->scheduled_end)),
            sessionId: $session->id, resolvedVia: 'default');
    }

    /** @return array{string, User} */
    private function teacher(string $org): array
    {
        $user = User::factory()->inOrganization($org)->create(['name' => 'معلمة', 'locale' => 'ar']);
        $staff = (string) Str::ulid();
        DB::table('staff_profiles')->insert(['id' => $staff, 'organization_id' => $org, 'user_id' => $user->id,
            'staff_code' => 'B-'.Str::random(8), 'employment_type' => 'part_time', 'created_at' => now(), 'updated_at' => now()]);

        return [$staff, $user];
    }

    /** @param list<string> $permissions */
    private function actor(string $org, array $permissions): User
    {
        $actor = User::factory()->inOrganization($org)->create(['locale' => 'ar', 'timezone' => 'Africa/Cairo']);
        foreach ($permissions as $name) {
            $permission = Permission::query()->where('name', $name)->sole();
            ModelHasPermission::query()->create(['permission_id' => $permission->id,
                'model_type' => $actor->getMorphClass(), 'model_id' => $actor->id]);
        }

        return $actor;
    }

    private function admin(string $org): User
    {
        return $this->actor($org, ['admin.panel.access', 'session.view', 'student.view.any', 'payroll.view', 'session.finalize']);
    }

    private function lesson(string $org, string $staff, Course $course, int $startsIn, int $endsIn,
        SessionStatus $status = SessionStatus::Scheduled): Session
    {
        $now = CarbonImmutable::now('UTC');

        return Session::query()->create(['organization_id' => $org, 'course_id' => $course->id,
            'staff_profile_id' => $staff, 'original_teacher_id' => $staff, 'session_type' => 'regular',
            'status' => $status, 'scheduled_start' => $now->addMinutes($startsIn),
            'scheduled_end' => $now->addMinutes($endsIn), 'title' => ['ar' => 'حصة']]);
    }

    private function room(Session $session, bool $started): void
    {
        DB::table('classrooms')->insert(['id' => (string) Str::ulid(), 'session_id' => $session->id,
            'provider' => 'bbb', 'status' => 'ready', 'health_status' => 'unknown',
            'max_concurrent_participants' => 0, 'link_generation' => 1, 'provision_attempts' => 0,
            'started_at' => $started ? CarbonImmutable::now('UTC')->subMinutes(590) : null,
            'created_at' => now(), 'updated_at' => now()]);
    }
}
