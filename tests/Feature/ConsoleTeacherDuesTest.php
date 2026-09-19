<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Academics\Domain\Models\Course;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Groups\Domain\Models\Group;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Payroll\Application\Actions\RecordPayrollEntryAction;
use Modules\Payroll\Domain\Contracts\TeacherDuesQueries;
use Modules\Payroll\Domain\Enums\PayrollPeriodStatus;
use Modules\Payroll\Domain\Models\PayrollAdjustment;
use Modules\Payroll\Domain\Models\PayrollEntry;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Modules\Payroll\Domain\ValueObjects\TeacherDuesMoney;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\ValueObjects\Money;
use Shared\ValueObjects\TimeRange;
use Tests\TestCase;

final class ConsoleTeacherDuesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['console.enabled' => true, 'features.payroll' => true]);
        /*
         * الإنتاج يعمل اليوم بمعتمِد واحد. الاختبارات القائمة تغطي حالة
         * المعتمِد المختلف، فتُثبَّت هنا صراحةً بدل أن ترث قيمة الإعداد —
         * وتُطفئها الاختبارات التي تقصد الحالة الأخرى.
         */
        config(['payroll.adjustments.requires_different_approver' => true]);
        $this->seed(AccessControlSeeder::class);
        app(PermissionGateRegistrar::class)->register();
    }

    public function test_real_permissions_features_and_organization_scope_protect_the_center(): void
    {
        [$org, $staff, $contract, $course, $period] = $this->context();
        [$foreignOrg, $foreignStaff, $foreignContract, $foreignCourse, $foreignPeriod] = $this->context();
        $this->get('/manage/teacher-dues')->assertRedirect(route('login'));
        $this->actingAs($this->actor($org, ['payroll.view']))->get('/manage/teacher-dues')->assertForbidden();
        $this->actingAs($this->actor($org, ['admin.panel.access']))->get('/manage/teacher-dues')->assertForbidden();
        $viewer = $this->actor($org, ['admin.panel.access', 'payroll.view']);
        $this->actingAs($viewer)->get('/manage/teacher-dues?period='.$period->id.'&teacher='.$staff)
            ->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('Console/TeacherDues')
            ->where('locale', 'ar')->where('direction', 'rtl')->where('canPropose', false)
            ->has('periods', 1)->where('period.id', $period->id)->where('detail.id', $staff)->where('detail.profileUrl', null));
        $this->get('/manage/teacher-dues?period='.$foreignPeriod->id)->assertNotFound();
        $this->get('/manage/teacher-dues?period='.$period->id.'&teacher='.$foreignStaff)->assertNotFound();
        $this->get('/manage/teacher-dues?period='.$period->id.'&staff_profile_id='.$foreignStaff)->assertNotFound();
        $this->post($this->proposalUrl($period), $this->proposal($staff))->assertForbidden();
        config(['features.payroll' => false]);
        $this->get('/manage/teacher-dues')->assertNotFound();
        config(['features.payroll' => true, 'console.enabled' => false]);
        $this->get('/manage/teacher-dues')->assertNotFound();
        $this->assertDatabaseCount('payroll_adjustments', 0);
    }

    public function test_lesson_counts_and_snapshot_amounts_do_not_recalculate_the_historical_ledger(): void
    {
        [$org, $staff, $contract, $course, $period] = $this->context();
        $viewer = $this->actor($org, ['admin.panel.access', 'payroll.view']);
        $group = Group::factory()->create(['organization_id' => $org, 'name' => ['ar' => 'مجموعة القرآن']]);
        $complete = $this->lesson($org, $staff, $course, SessionStatus::Completed, 2, ['group_id' => $group->id, 'actual_start' => '2026-08-02 12:00:00', 'actual_end' => '2026-08-02 12:50:00']);
        foreach (range(1, 2) as $index) {
            $student = StudentProfile::factory()->create(['organization_id' => $org]);
            $enrollment = (string) Str::ulid();
            DB::table('enrollments')->insert(['id' => $enrollment, 'organization_id' => $org,
                'student_profile_id' => $student->id, 'program_id' => DB::table('levels')->where('id', $course->level_id)->value('program_id'),
                'status' => 'active', 'applied_at' => now(), 'activated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('session_participants')->insert(['id' => (string) Str::ulid(), 'session_id' => $complete->id,
                'student_profile_id' => $student->id, 'enrollment_id' => $enrollment,
                'join_url_token' => Str::random(64), 'invited_at' => now(), 'created_at' => now()]);
        }
        $pending = $this->lesson($org, $staff, $course, SessionStatus::AwaitingReview, 3);
        $this->lesson($org, $staff, $course, SessionStatus::CancelledBySchool, 4);
        $this->lesson($org, $staff, $course, SessionStatus::Scheduled, 5);
        $entry = $this->entry($org, $staff, $contract, $period, $complete, 5010);
        $before = $entry->fresh()->getRawOriginal();
        DB::table('teacher_rates')->where('teacher_contract_id', $contract)->update(['amount' => 99900]);
        $this->actingAs($viewer)->get('/manage/teacher-dues?period='.$period->id.'&teacher='.$staff)
            ->assertOk()->assertInertia(fn (Assert $page): Assert => $page->where('teachers.0.counts.delivered', 2)
            ->where('teachers.0.counts.approved', 1)->where('teachers.0.counts.pending', 1)
            ->where('teachers.0.counts.cancelled', 1)->where('teachers.0.counts.minutes', 110)
            ->where('detail.totals.0.amounts.entryNet', '50.10')->where('detail.totals.0.amounts.remaining', '50.10')
            ->where('detail.entries.0.snapshotAmount', '50.10')->where('detail.entries.0.amount', '50.10')
            ->has('detail.lessons', 4)
            ->where('detail.lessons', fn ($lessons): bool => collect($lessons)->firstWhere('id', $pending->id)['entries'] === [])
            ->where('detail.lessons', fn ($lessons): bool => collect($lessons)->firstWhere('id', $complete->id)['studentCount'] === 2));
        $this->assertSame($before, $entry->fresh()->getRawOriginal());
        $this->assertDatabaseCount('payroll_entries', 1);
        $this->assertDatabaseCount('payroll_adjustments', 0);
    }

    public function test_bonus_proposal_and_independent_approval_append_without_editing_session_entries(): void
    {
        [$org, $staff, $contract, $course, $period] = $this->context();
        $proposer = $this->actor($org, ['admin.panel.access', 'payroll.view', 'payroll.adjustment.propose', 'payroll.adjustment.approve']);
        $approver = $this->actor($org, ['admin.panel.access', 'payroll.view', 'payroll.adjustment.approve']);
        $entry = $this->entry($org, $staff, $contract, $period, $this->lesson($org, $staff, $course), 5000);
        $before = $entry->fresh()->getRawOriginal();
        $url = '/manage/teacher-dues?period='.$period->id.'&teacher='.$staff;
        $this->actingAs($proposer)->post($this->proposalUrl($period), $this->proposal($staff))
            ->assertRedirect($url)->assertSessionHasNoErrors();
        $adjustment = PayrollAdjustment::query()->sole();
        $this->assertSame(2510, $adjustment->amount);
        $this->assertSame($proposer->id, $adjustment->proposed_by);
        $this->assertNotSame('', $adjustment->reason);
        $this->get($url)->assertInertia(fn (Assert $page): Assert => $page->where('detail.totals.0.amounts.net', '50.00')
            ->where('detail.totals.0.amounts.pendingAdjustments', '25.10')->where('detail.adjustments.0.canApprove', false));
        $this->post($this->decisionUrl($adjustment), ['reason_category' => 'reviewed'])->assertForbidden();
        $this->actingAs($approver)->get($url)->assertInertia(fn (Assert $page): Assert => $page->where('detail.adjustments.0.canApprove', true));
        $this->post($this->decisionUrl($adjustment), ['reason_category' => 'reviewed'])->assertRedirect($url)->assertSessionHasNoErrors();
        $this->get($url)->assertInertia(fn (Assert $page): Assert => $page->where('detail.totals.0.amounts.net', '75.10')
            ->where('detail.totals.0.amounts.bonus', '25.10')->where('detail.totals.0.amounts.pendingAdjustments', '0.00')
            ->where('detail.adjustments.0.approvedBy', $approver->id)->where('detail.adjustments.0.canApprove', false));
        $this->post($this->decisionUrl($adjustment), ['reason_category' => 'reviewed'])->assertForbidden();
        $this->assertSame($before, $entry->fresh()->getRawOriginal());
        $this->assertDatabaseCount('payroll_entries', 1);
        $this->assertDatabaseHas('audit_log', ['action' => 'payroll.adjustment.proposed', 'actor_id' => $proposer->id, 'auditable_id' => $adjustment->id]);
        $this->assertDatabaseHas('audit_log', ['action' => 'payroll.adjustment.approved', 'actor_id' => $approver->id, 'auditable_id' => $adjustment->id]);
    }

    public function test_deductions_use_exact_signed_minor_units_and_rejected_items_never_change_net(): void
    {
        [$org, $staff, $contract, $course, $period] = $this->context();
        $proposer = $this->actor($org, ['admin.panel.access', 'payroll.view', 'payroll.adjustment.propose']);
        $approver = $this->actor($org, ['admin.panel.access', 'payroll.view', 'payroll.adjustment.approve']);
        $this->actingAs($proposer)->post($this->proposalUrl($period), $this->proposal($staff, ['type' => 'deduction', 'amount' => '10.25']))->assertSessionHasNoErrors();
        $deduction = PayrollAdjustment::query()->sole();
        $this->assertSame(-1025, $deduction->amount);
        $this->actingAs($approver)->post($this->decisionUrl($deduction), ['reason_category' => 'reviewed'])->assertSessionHasNoErrors();
        $this->actingAs($proposer)->post($this->proposalUrl($period), $this->proposal($staff))->assertSessionHasNoErrors();
        $bonus = PayrollAdjustment::query()->where('type', 'bonus')->sole();
        $this->actingAs($approver)->post($this->decisionUrl($bonus, 'reject'), ['reason_category' => 'incomplete'])->assertSessionHasNoErrors();
        $statement = app(TeacherDuesQueries::class)->statement($approver, $period->id);
        $this->assertSame('-10.25', $statement->teachers[0]['totals'][0]['amounts']['net']);
        $this->assertSame('0.00', $statement->teachers[0]['totals'][0]['amounts']['bonus']);
        $this->assertNotNull($bonus->fresh()->rejected_at);
        $this->assertDatabaseHas('audit_log', ['action' => 'payroll.adjustment.rejected', 'actor_id' => $approver->id]);
        $this->actingAs($proposer);
        foreach (['1.001', '1e3', '-5', '0', '١٢', '99999999999999999'] as $invalid) {
            $this->postJson($this->proposalUrl($period), $this->proposal($staff, ['amount' => $invalid]))->assertUnprocessable();
        }
        $this->postJson($this->proposalUrl($period), $this->proposal($staff, ['reason_category' => 'other', 'note' => '']))->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->assertDatabaseCount('payroll_adjustments', 2);
        $this->assertSame(9007199254740991, TeacherDuesMoney::fromMajor('90071992547409.91', 'EGP')->minorUnits);
        $this->assertSame('90071992547409.91', TeacherDuesMoney::display(9007199254740991));
        $this->assertSame('-0.01', TeacherDuesMoney::display(-1));
    }

    public function test_paid_period_rejects_changes_and_correction_is_appended_to_next_open_period(): void
    {
        [$org, $staff, $contract, $course, $period] = $this->context();
        $proposer = $this->actor($org, ['admin.panel.access', 'payroll.view', 'payroll.adjustment.propose']);
        $approver = $this->actor($org, ['admin.panel.access', 'payroll.view', 'payroll.adjustment.approve']);
        $entry = $this->entry($org, $staff, $contract, $period, $this->lesson($org, $staff, $course), 5000);
        $before = $entry->fresh()->getRawOriginal();
        $this->actingAs($proposer)->post($this->proposalUrl($period), $this->proposal($staff))->assertSessionHasNoErrors();
        $pending = PayrollAdjustment::query()->sole();
        // Fixture represents a previously paid period; the new UI has no synthetic payment action.
        $period->update(['status' => PayrollPeriodStatus::Paid, 'paid_at' => now()]);
        $this->postJson($this->proposalUrl($period), $this->proposal($staff))->assertUnprocessable()->assertJsonPath('error.code', 'payroll.period.frozen');
        $this->actingAs($approver)->postJson($this->decisionUrl($pending), ['reason_category' => 'reviewed'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'payroll.period.frozen');
        $this->get('/manage/teacher-dues?period='.$period->id.'&teacher='.$staff)
            ->assertInertia(fn (Assert $page): Assert => $page->where('period.canAdjust', false)
                ->where('detail.adjustments.0.canApprove', false)->where('detail.totals.0.amounts.paid', '50.00')
                ->where('detail.totals.0.amounts.remaining', '0.00'));
        $next = $this->period($org, 9);
        $this->actingAs($proposer)->post($this->proposalUrl($next), $this->proposal($staff, [
            'type' => 'correction', 'amount' => '5.05', 'reason_category' => 'previous_error', 'references_period_id' => $period->id,
        ]))->assertRedirect('/manage/teacher-dues?period='.$next->id.'&teacher='.$staff)->assertSessionHasNoErrors();
        $correction = PayrollAdjustment::query()->where('payroll_period_id', $next->id)->sole();
        $this->assertSame($period->id, $correction->references_period_id);
        $this->assertSame(505, $correction->amount);
        $this->assertSame($before, $entry->fresh()->getRawOriginal());
        $this->assertNull($pending->fresh()->approved_at);
        $this->assertDatabaseCount('payroll_entries', 1);
    }

    public function test_write_boundaries_reject_foreign_teacher_period_reference_and_adjustment(): void
    {
        [$org, $staff, $contract, $course, $period] = $this->context();
        [$foreignOrg, $foreignStaff, $foreignContract, $foreignCourse, $foreignPeriod] = $this->context();
        $permissions = ['admin.panel.access', 'payroll.view', 'payroll.adjustment.propose', 'payroll.adjustment.approve'];
        $actor = $this->actor($org, $permissions);
        $foreignActor = $this->actor($foreignOrg, $permissions);
        $this->actingAs($foreignActor)->post($this->proposalUrl($foreignPeriod), $this->proposal($foreignStaff))->assertSessionHasNoErrors();
        $foreignAdjustment = PayrollAdjustment::query()->sole();
        $this->actingAs($actor)->postJson($this->proposalUrl($foreignPeriod), $this->proposal($staff))->assertNotFound();
        $this->postJson($this->proposalUrl($period), $this->proposal($foreignStaff))->assertUnprocessable()->assertJsonPath('error.code', 'payroll.adjustment.staff_not_found');
        $this->postJson($this->proposalUrl($period), $this->proposal($staff, ['references_period_id' => $foreignPeriod->id]))
            ->assertUnprocessable()->assertJsonPath('error.code', 'payroll.adjustment.reference_period_not_found');
        $this->postJson($this->decisionUrl($foreignAdjustment), ['reason_category' => 'reviewed'])->assertNotFound();
        $this->assertDatabaseCount('payroll_adjustments', 1);
        $this->assertNull($foreignAdjustment->fresh()->approved_at);
    }

    public function test_deferred_entries_and_pending_bonuses_are_visible_but_excluded_from_remaining(): void
    {
        [$org, $staff, $contract, $course, $period] = $this->context();
        $actor = $this->actor($org, ['admin.panel.access', 'payroll.view', 'payroll.adjustment.propose']);
        $complete = $this->lesson($org, $staff, $course);
        $makeup = $this->lesson($org, $staff, $course, SessionStatus::Scheduled, 3);
        $this->entry($org, $staff, $contract, $period, $complete, 5000, $makeup->id);
        $this->actingAs($actor)->post($this->proposalUrl($period), $this->proposal($staff))->assertSessionHasNoErrors();
        $this->get('/manage/teacher-dues?period='.$period->id.'&teacher='.$staff)
            ->assertInertia(fn (Assert $page): Assert => $page->where('detail.totals.0.amounts.deferred', '50.00')
                ->where('detail.totals.0.amounts.remaining', '0.00')->where('detail.totals.0.amounts.pendingAdjustments', '25.10')
                ->where('detail.entries.0.status', 'deferred'));
        config(['reporting.operational.max_rows' => 1]);
        $this->get('/manage/teacher-dues?period='.$period->id.'&teacher='.$staff)
            ->assertInertia(fn (Assert $page): Assert => $page->where('limitExceeded', true)->where('detail.counts', null)
                ->where('teachers.0.counts', null)->where('detail.totals.0.amounts.deferred', '50.00'));
    }

    public function test_empty_period_is_honest_and_bonus_only_teachers_remain_visible(): void
    {
        [$org, $staff, $contract, $course, $period] = $this->context();
        $actor = $this->actor($org, ['admin.panel.access', 'payroll.view', 'payroll.adjustment.propose']);
        $this->actingAs($actor)->get('/manage/teacher-dues?period='.$period->id)
            ->assertInertia(fn (Assert $page): Assert => $page->has('teachers', 0)->where('detail', null));
        $this->post($this->proposalUrl($period), $this->proposal($staff))->assertSessionHasNoErrors();
        $this->get('/manage/teacher-dues?period='.$period->id)->assertInertia(fn (Assert $page): Assert => $page
            ->has('teachers', 1)->where('teachers.0.id', $staff)->where('teachers.0.counts.delivered', 0)
            ->where('teachers.0.totals.0.amounts.pendingAdjustments', '25.10')->where('teachers.0.totals.0.amounts.remaining', '0.00'));
    }

    public function test_archived_teacher_statement_remains_readable_without_reactivation_or_new_adjustment(): void
    {
        [$org, $staff, $contract, $course, $period] = $this->context();
        $actor = $this->actor($org, ['admin.panel.access', 'payroll.view', 'payroll.adjustment.propose', 'staff.view.any']);
        $entry = $this->entry($org, $staff, $contract, $period, $this->lesson($org, $staff, $course), 5010);
        $before = $entry->fresh()->getRawOriginal();
        DB::table('staff_profiles')->where('id', $staff)->update(['deleted_at' => now()]);
        $url = '/manage/teacher-dues?period='.$period->id.'&teacher='.$staff;
        $this->actingAs($actor)->get('/manage/teacher-dues?period='.$period->id)->assertInertia(fn (Assert $page): Assert => $page
            ->where('teachers.0.id', $staff)->where('teachers.0.detailUrl', url($url)));
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->where('detail.id', $staff)->where('detail.canPropose', false)->where('detail.profileUrl', null)
            ->where('detail.totals.0.amounts.net', '50.10')->has('detail.entries', 1));
        $this->postJson($this->proposalUrl($period), $this->proposal($staff))->assertUnprocessable()
            ->assertJsonPath('error.code', 'payroll.adjustment.staff_not_found');
        [$otherOrg, $foreignStaff, $foreignContract, $foreignCourse, $foreignPeriod] = $this->context();
        $this->entry($otherOrg, $foreignStaff, $foreignContract, $foreignPeriod, $this->lesson($otherOrg, $foreignStaff, $foreignCourse), 9000);
        DB::table('staff_profiles')->where('id', $foreignStaff)->update(['deleted_at' => now()]);
        $this->get('/manage/teacher-dues?period='.$period->id.'&teacher='.$foreignStaff)->assertNotFound();
        $this->assertNotNull(DB::table('staff_profiles')->where('id', $staff)->value('deleted_at'));
        $this->assertSame($before, $entry->fresh()->getRawOriginal());
        $this->assertDatabaseCount('payroll_adjustments', 0);
    }

    public function test_filtered_original_teacher_statement_includes_substitute_lesson_without_counting_it_as_delivered(): void
    {
        [$org, $staff, $contract, $course, $period] = $this->context();
        $actor = $this->actor($org, ['admin.panel.access', 'payroll.view']);
        $substituteUser = User::factory()->inOrganization($org)->create(['name' => 'المعلم البديل']);
        $substitute = (string) Str::ulid();
        DB::table('staff_profiles')->insert(['id' => $substitute, 'organization_id' => $org, 'user_id' => $substituteUser->id,
            'staff_code' => 'SUB-'.Str::random(8), 'employment_type' => 'part_time', 'created_at' => now(), 'updated_at' => now()]);
        $lesson = $this->lesson($org, $substitute, $course, SessionStatus::Completed, 2, ['original_teacher_id' => $staff]);
        $entry = $this->entry($org, $staff, $contract, $period, $lesson, -5000);
        foreach (['', '&staff_profile_id='.$staff] as $filter) {
            $this->actingAs($actor)->get('/manage/teacher-dues?period='.$period->id.'&teacher='.$staff.$filter)
                ->assertOk()->assertInertia(fn (Assert $page): Assert => $page
                ->where('detail.counts.delivered', 0)->where('detail.counts.minutes', 0)->has('detail.lessons', 1)
                ->where('detail.lessons.0.id', $lesson->id)->where('detail.lessons.0.isActualTeacher', false)
                ->where('detail.lessons.0.actualTeacher', 'المعلم البديل')->where('detail.lessons.0.entries.0.id', $entry->id)
                ->where('detail.totals.0.amounts.net', '-50.00'));
        }
        $this->assertDatabaseCount('payroll_entries', 1);
    }

    public function test_approver_separation_follows_configuration_not_hardcoded_policy(): void
    {
        [$org, $staff, $contract, $course, $period] = $this->context();
        $actor = $this->actor($org, ['admin.panel.access', 'payroll.view', 'payroll.adjustment.propose', 'payroll.adjustment.approve']);
        $this->actingAs($actor)->post($this->proposalUrl($period), $this->proposal($staff))->assertSessionHasNoErrors();
        $adjustment = PayrollAdjustment::query()->sole();
        $url = '/manage/teacher-dues?period='.$period->id.'&teacher='.$staff;

        // الافتراضي: من يقترح لا يعتمد، والواجهة تقول ذلك للمستخدم.
        $this->get($url)->assertInertia(fn (Assert $page): Assert => $page->where('requiresSecondApprover', true)
            ->where('detail.adjustments.0.canApprove', false));
        $this->assertFalse(Gate::forUser($actor)->allows('approve', $adjustment));

        /*
         * السياسة كانت تثبّت هذا الفصل في الكود بينما الإجراء يقرأه من
         * الإعدادات، فكان إطفاء الإعداد لا يغيّر شيئًا. الآن الطبقتان تقرآن
         * المصدر نفسه.
         */
        config(['payroll.adjustments.requires_different_approver' => false]);
        $this->assertTrue(Gate::forUser($actor)->allows('approve', $adjustment));
        $this->get($url)->assertInertia(fn (Assert $page): Assert => $page->where('requiresSecondApprover', false)
            ->where('detail.adjustments.0.canApprove', true));

        /*
         * الاعتماد الفعلي ما زال ممنوعًا على مستوى قاعدة البيانات بقيد
         * `payroll_adjustments_approval_separation_check`. إطفاء الإعداد وحده
         * لا يفتح المسار، ورفع القيد قرار مالك لم يُنفَّذ. هذا الاختبار يثبّت
         * الحقيقة كما هي حتى لا يُظن أن الخطوة الواحدة صارت تعمل.
         */
        $this->assertNull($adjustment->fresh()->approved_at);
        $this->assertDatabaseCount('payroll_adjustments', 1);
    }

    public function test_past_scheduled_lesson_is_counted_undecided_and_approved_from_the_statement(): void
    {
        [$org, $staff, $contract, $course, $period] = $this->context();
        $actor = $this->decider($org);
        $lesson = $this->lesson($org, $staff, $course, SessionStatus::Scheduled, 5);
        $url = '/manage/teacher-dues?period='.$period->id.'&teacher='.$staff;
        $this->actingAs($actor)->get($url)->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->where('detail.counts.undecided', 1)
                ->where('detail.counts.delivered', 0)->where('detail.lessons.0.neverStarted', true)
                ->where('detail.lessons.0.needsDecision', true)->where('detail.lessons.0.rateKnown', true)
                ->where('detail.lessons.0.suggestedAmount', '50.00')
                ->where('canDecide.complete', true));
        $this->post($this->sessionDecisionUrl($lesson), $this->sessionDecision())->assertSessionHasNoErrors();
        $this->assertSame(SessionStatus::Completed, $lesson->fresh()->status);
        $entry = PayrollEntry::query()->where('session_id', $lesson->id)->sole();
        $this->assertSame(5000, $entry->amount);
        $this->assertNull($lesson->fresh()->payroll_rate_override_minor_units);
        $this->get($url)->assertInertia(fn (Assert $page): Assert => $page->where('detail.counts.undecided', 0)
            ->where('detail.counts.approved', 1)->where('detail.lessons.0.needsDecision', false)
            ->where('detail.totals.0.amounts.entryNet', '50.00'));
        // المسار واحد: القرار يمر بنفس تاريخ الحالات الذي تكتبه شاشة الاعتماد.
        $this->assertDatabaseHas('session_status_history', ['session_id' => $lesson->id, 'to_status' => 'awaiting_review']);
        $this->assertDatabaseHas('audit_log', ['action' => 'sessions.session_recorded_off_platform', 'auditable_id' => $lesson->id]);
    }

    public function test_manual_amount_prices_one_lesson_without_touching_the_teacher_rate(): void
    {
        [$org, $staff, $contract, $course, $period] = $this->context();
        $actor = $this->decider($org);
        $lesson = $this->lesson($org, $staff, $course, SessionStatus::Scheduled, 6);
        $this->actingAs($actor)->post($this->sessionDecisionUrl($lesson), $this->sessionDecision(['amount' => '70.00']))
            ->assertSessionHasNoErrors();
        $entry = PayrollEntry::query()->where('session_id', $lesson->id)->sole();
        $this->assertSame(7000, $entry->amount);
        $this->assertSame(7000, (int) $lesson->fresh()->payroll_rate_override_minor_units);
        // سعر المعلم نفسه لم يُمس: الأجر اليدوي يخص هذه الحصة وحدها.
        $this->assertSame(5000, (int) DB::table('teacher_rates')->where('teacher_contract_id', $contract)->value('amount'));
        $this->assertDatabaseHas('audit_log', ['action' => 'sessions.payroll_rate_override_set', 'auditable_id' => $lesson->id]);
        $later = $this->lesson($org, $staff, $course, SessionStatus::Scheduled, 7);
        $this->post($this->sessionDecisionUrl($later), $this->sessionDecision())->assertSessionHasNoErrors();
        $this->assertSame(5000, PayrollEntry::query()->where('session_id', $later->id)->sole()->amount);
    }

    public function test_session_decision_rejects_stale_state_bad_amounts_and_missing_ability(): void
    {
        [$org, $staff, $contract, $course, $period] = $this->context();
        $lesson = $this->lesson($org, $staff, $course, SessionStatus::Scheduled, 8);
        $reader = $this->actor($org, ['admin.panel.access', 'payroll.view', 'session.view', 'student.view.any']);
        $this->actingAs($reader)->post($this->sessionDecisionUrl($lesson), $this->sessionDecision())->assertForbidden();
        $this->actingAs($reader)->get('/manage/teacher-dues?period='.$period->id.'&teacher='.$staff)
            ->assertInertia(fn (Assert $page): Assert => $page->where('canDecide.complete', false)
                ->where('detail.lessons.0.needsDecision', true));
        $actor = $this->decider($org);
        $this->actingAs($actor)->postJson($this->sessionDecisionUrl($lesson), $this->sessionDecision(['expected_status' => 'awaiting_review']))
            ->assertUnprocessable()->assertJsonValidationErrors('decision');
        $this->postJson($this->sessionDecisionUrl($lesson), $this->sessionDecision(['amount' => '-5']))->assertUnprocessable();
        $this->postJson($this->sessionDecisionUrl($lesson), $this->sessionDecision(['reason_category' => 'other', 'note' => '']))
            ->assertUnprocessable()->assertJsonValidationErrors('note');
        // الأجر اليدوي لا معنى له مع قرار غير الاعتماد، فيُرفض بدل أن يُهمل بصمت.
        $this->postJson($this->sessionDecisionUrl($lesson), $this->sessionDecision([
            'decision' => 'cancelled_by_school', 'reason_category' => 'not_held', 'amount' => '70.00',
        ]))->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->assertSame(SessionStatus::Scheduled, $lesson->fresh()->status);
        $this->assertDatabaseCount('payroll_entries', 0);
        $this->post($this->sessionDecisionUrl($lesson), $this->sessionDecision([
            'decision' => 'cancelled_by_school', 'reason_category' => 'not_held',
        ]))->assertSessionHasNoErrors();
        $this->assertSame(SessionStatus::CancelledBySchool, $lesson->fresh()->status);
    }

    public function test_a_closed_period_refuses_the_decision_instead_of_closing_a_lesson_with_no_entry(): void
    {
        [$org, $staff, $contract, $course, $period] = $this->context();
        $actor = $this->decider($org);
        $lesson = $this->lesson($org, $staff, $course, SessionStatus::Scheduled, 9);
        $period->update(['status' => PayrollPeriodStatus::Paid, 'paid_at' => now()]);

        /*
         * بلا هذا الحارس كانت الحصة تُقفل نهائيًا بينما يرفض الدفتر قيدتها
         * ويبتلع المستمع الرفض في السجل — حصة منتهية بلا مستحق لا يعرف بها
         * أحد، ولا تُصحَّح على دفتر append-only إلا بتسوية يدوية.
         */
        $this->actingAs($actor)->postJson($this->sessionDecisionUrl($lesson), $this->sessionDecision())
            ->assertUnprocessable()->assertJsonValidationErrors(['decision' => __('console_dues.session_period_closed')]);
        $this->assertSame(SessionStatus::Scheduled, $lesson->fresh()->status);
        $this->assertDatabaseCount('payroll_entries', 0);
    }

    public function test_a_failed_decision_never_leaves_a_manual_price_behind(): void
    {
        [$org, $staff, $contract, $course, $period] = $this->context();
        $actor = $this->decider($org);
        // حصة لم ينتهِ موعدها بعد: الاعتماد يُرفض داخل الإجراء.
        $future = CarbonImmutable::now('UTC')->addDays(3);
        $lesson = Session::query()->create(['organization_id' => $org, 'course_id' => $course->id,
            'staff_profile_id' => $staff, 'original_teacher_id' => $staff, 'session_type' => 'regular',
            'status' => SessionStatus::Scheduled, 'scheduled_start' => $future, 'scheduled_end' => $future->addHour(),
            'title' => ['ar' => 'حصة القرآن']]);

        $this->actingAs($actor)->postJson($this->sessionDecisionUrl($lesson), $this->sessionDecision(['amount' => '700.00']))
            ->assertUnprocessable();

        /*
         * السعر اليدوي كان يُكتب قبل الانتقال، فيبقى على حصة لم تُعتمد ثم
         * يُدفع به لاحقًا عند اعتمادها الطبيعي بسعر لم يقصده أحد.
         */
        $this->assertNull($lesson->fresh()->payroll_rate_override_minor_units);
        $this->assertDatabaseMissing('audit_log', ['action' => 'sessions.payroll_rate_override_set', 'auditable_id' => $lesson->id]);
        $this->assertSame(SessionStatus::Scheduled, $lesson->fresh()->status);
    }

    public function test_manual_price_is_refused_where_it_would_never_be_read(): void
    {
        [$org, $staff, $contract, $course, $period] = $this->context();
        $actor = $this->decider($org);

        // تعويضية: قيدتها هي قيدة الأصلية المؤجَّلة، فلا تُسعَّر بنفسها.
        $original = $this->lesson($org, $staff, $course, SessionStatus::Postponed, 10);
        $makeup = $this->lesson($org, $staff, $course, SessionStatus::Scheduled, 11, ['makeup_for_session_id' => $original->id]);
        $this->actingAs($actor)->postJson($this->sessionDecisionUrl($makeup), $this->sessionDecision(['amount' => '80.00']))
            ->assertUnprocessable()->assertJsonValidationErrors(['decision' => __('console_dues.override_on_makeup')]);

        // معفاة من المستحقات: لا قيدة لها أصلًا.
        $exempt = $this->lesson($org, $staff, $course, SessionStatus::Scheduled, 12, ['payroll_exempt' => true]);
        $this->postJson($this->sessionDecisionUrl($exempt), $this->sessionDecision(['amount' => '80.00']))
            ->assertUnprocessable()->assertJsonValidationErrors(['decision' => __('console_dues.override_on_exempt')]);

        // فوق السقف المعرَّف في الإعدادات — خطأ مطبعي لا قيدة بمئة ضعف.
        $plain = $this->lesson($org, $staff, $course, SessionStatus::Scheduled, 13);
        $over = (int) config('payroll.session_manual_amount_max_minor_units') / 100 + 1;
        $this->postJson($this->sessionDecisionUrl($plain), $this->sessionDecision(['amount' => $over.'.00']))
            ->assertUnprocessable()->assertJsonValidationErrors('decision');

        // بلا عقد سارٍ لا تُنسب القيدة إلى شيء، فيخرج المستمع بصفر صامت.
        DB::table('teacher_contracts')->where('id', $contract)->update(['effective_to' => '2026-01-02']);
        $this->postJson($this->sessionDecisionUrl($plain), $this->sessionDecision(['amount' => '60.00']))
            ->assertUnprocessable()->assertJsonValidationErrors(['decision' => __('console_dues.override_without_contract')]);

        $this->assertDatabaseCount('payroll_entries', 0);
        $this->assertNull($plain->fresh()->payroll_rate_override_minor_units);
        $this->assertSame(SessionStatus::Scheduled, $plain->fresh()->status);
    }

    private function decider(string $org): User
    {
        return $this->actor($org, ['admin.panel.access', 'payroll.view', 'session.view', 'session.finalize',
            'session.cancel', 'attendance.record', 'student.view.any']);
    }

    /** @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function sessionDecision(array $extra = []): array
    {
        return ['decision' => 'complete', 'expected_status' => 'scheduled',
            'reason_category' => 'off_platform', 'note' => '', ...$extra];
    }

    private function sessionDecisionUrl(Session $session): string
    {
        return '/manage/teacher-dues/sessions/'.$session->id.'/decision';
    }

    /** @return array{string, string, string, Course, PayrollPeriod} */
    private function context(): array
    {
        $org = (string) Organization::factory()->create()->id;
        $user = User::factory()->inOrganization($org)->create(['name' => 'معلم قرآن', 'locale' => 'ar']);
        $staff = (string) Str::ulid();
        DB::table('staff_profiles')->insert(['id' => $staff, 'organization_id' => $org, 'user_id' => $user->id,
            'staff_code' => 'D-'.Str::random(8), 'employment_type' => 'part_time', 'created_at' => now(), 'updated_at' => now()]);
        $contract = (string) Str::ulid();
        DB::table('teacher_contracts')->insert(['id' => $contract, 'organization_id' => $org, 'staff_profile_id' => $staff,
            'basis' => 'per_session', 'currency' => 'EGP', 'effective_from' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('teacher_rates')->insert(['id' => (string) Str::ulid(), 'teacher_contract_id' => $contract,
            'scope' => 'default', 'amount' => 5000, 'currency' => 'EGP', 'effective_from' => '2026-01-01', 'created_at' => now()]);
        $course = Course::factory()->create(['organization_id' => $org, 'name' => ['ar' => 'دورة القرآن']]);

        return [$org, $staff, $contract, $course, $this->period($org, 8)];
    }

    private function period(string $org, int $month): PayrollPeriod
    {
        $date = CarbonImmutable::create(2026, $month, 1, 0, 0, 0, 'UTC');

        return PayrollPeriod::query()->create(['organization_id' => $org, 'year' => 2026, 'month' => $month,
            'starts_on' => $date, 'ends_on' => $date->endOfMonth(), 'status' => PayrollPeriodStatus::Open, 'totals' => []]);
    }

    /** @param list<string> $permissions */
    private function actor(string $org, array $permissions): User
    {
        $actor = User::factory()->inOrganization($org)->create(['locale' => 'ar', 'timezone' => 'Africa/Cairo']);
        foreach ($permissions as $name) {
            $permission = Permission::query()->where('name', $name)->sole();
            ModelHasPermission::query()->create(['permission_id' => $permission->id, 'model_type' => $actor->getMorphClass(), 'model_id' => $actor->id]);
        }

        return $actor;
    }

    /** @param array<string, mixed> $extra */
    private function lesson(string $org, string $staff, Course $course, SessionStatus $status = SessionStatus::Completed, int $day = 2, array $extra = []): Session
    {
        $start = CarbonImmutable::create(2026, 8, $day, 12, 0, 0, 'UTC');

        return Session::query()->create(['organization_id' => $org, 'course_id' => $course->id, 'staff_profile_id' => $staff,
            'original_teacher_id' => $staff, 'session_type' => 'regular', 'status' => $status, 'scheduled_start' => $start,
            'scheduled_end' => $start->addHour(), 'title' => ['ar' => 'حصة القرآن'], ...$extra]);
    }

    private function entry(string $org, string $staff, string $contract, PayrollPeriod $period, Session $session, int $amount, ?string $deferredUntil = null): PayrollEntry
    {
        return app(RecordPayrollEntryAction::class)->execute($org, $period->id, $staff, $contract, 'session_earning', 'completed',
            Money::of($amount, 'EGP'), new TimeRange(CarbonImmutable::parse($session->scheduled_start), CarbonImmutable::parse($session->scheduled_end)),
            sessionId: $session->id, deferredUntilSessionId: $deferredUntil, resolvedVia: 'default');
    }

    /** @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function proposal(string $staff, array $extra = []): array
    {
        return ['staff_profile_id' => $staff, 'type' => 'bonus', 'amount' => '25.10', 'reason_category' => 'extra_work', 'note' => '', ...$extra];
    }

    private function proposalUrl(PayrollPeriod $period): string
    {
        return '/manage/teacher-dues/periods/'.$period->id.'/adjustments';
    }

    private function decisionUrl(PayrollAdjustment $adjustment, string $decision = 'approve'): string
    {
        return '/manage/teacher-dues/adjustments/'.$adjustment->id.'/'.$decision;
    }
}
