<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Actions\BulkAssignStudentsToGroupAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Groups\Application\Actions\ActivateGroupAction;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupMembership;
use Modules\Groups\Domain\Models\GroupProgram;
use Modules\Groups\Domain\Models\GroupTeacher;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;
use Tests\TestCase;

/**
 * التسكين الجماعي من شاشة طلبات التسجيل.
 *
 * الاختبارات هنا تحرس العقد كاملًا: المسار القانوني للتسجيل، الذرية، السعة،
 * منع التكرار، عزل المؤسسة، ودورة حياة المسودة.
 */
final class BulkAssignStudentsToGroupTest extends TestCase
{
    use RefreshDatabase;

    private string $organizationId;

    private string $programId;

    private string $courseId;

    private string $countryId;

    private string $regionId;

    private string $actorId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GeographySeeder::class);

        $this->organizationId = Fixtures::organizationId();
        $this->actorId = Fixtures::userId();
        $this->courseId = Fixtures::courseId();
        $this->programId = (string) DB::table('courses')
            ->join('levels', 'courses.level_id', '=', 'levels.id')
            ->where('courses.id', $this->courseId)
            ->value('levels.program_id');

        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);
        $country = $geography->findCountryByIso2('EG');
        $this->countryId = $country->id;
        $this->regionId = $geography->regionsOf($country->id)[0]->id;
    }

    // ── المسار الأول: مجموعة موجودة ──────────────────────────────────────

    public function test_it_places_several_students_into_an_existing_group(): void
    {
        $group = $this->activeGroup(capacity: 5);
        $applications = [$this->clearedApplication('طالب أول'), $this->clearedApplication('طالب ثانٍ')];

        $result = $this->action()->execute(
            actorOrganizationId: $this->organizationId,
            applicationIds: $this->idsOf($applications),
            programId: $this->programId,
            courseId: $this->courseId,
            groupId: (string) $group->getKey(),
            newGroupName: null,
            timezone: 'UTC',
            reason: 'توزيع دفعة القبول الشهرية',
            actorId: $this->actorId,
        );

        $this->assertSame(2, $result->placedCount());
        $this->assertFalse($result->groupWasCreated);
        $this->assertFalse($result->groupIsDraft);

        foreach ($applications as $application) {
            $this->assertDatabaseHas('group_memberships', [
                'group_id' => (string) $group->getKey(),
                'student_profile_id' => (string) $application->student_profile_id,
                'status' => MembershipStatus::Active->value,
            ]);

            // القيد الرسمي أُنشئ عبر مسار التسجيل لا بكتابة يدوية.
            $this->assertDatabaseHas('enrollments', [
                'student_profile_id' => (string) $application->student_profile_id,
                'program_id' => $this->programId,
                'status' => EnrollmentStatus::Active->value,
            ]);

            $this->assertSame(
                RegistrationStatus::Assigned,
                $application->refresh()->status,
            );
        }
    }

    public function test_it_creates_the_enrollment_through_the_official_action(): void
    {
        $group = $this->activeGroup(capacity: 3);
        $application = $this->clearedApplication('طالب بلا قيد');

        $this->assertDatabaseCount('enrollments', 0);

        $this->action()->execute(
            actorOrganizationId: $this->organizationId,
            applicationIds: [(string) $application->getKey()],
            programId: $this->programId,
            courseId: $this->courseId,
            groupId: (string) $group->getKey(),
            newGroupName: null,
            timezone: 'UTC',
            reason: 'قيد جديد مع التسكين',
            actorId: $this->actorId,
        );

        $enrollment = Enrollment::query()
            ->where('student_profile_id', (string) $application->student_profile_id)
            ->firstOrFail();

        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        // سجل الانتقال دليل مرور العملية بآلة الحالات لا بكتابة مباشرة.
        $this->assertDatabaseHas('enrollment_status_history', [
            'enrollment_id' => (string) $enrollment->getKey(),
            'to_status' => EnrollmentStatus::Active->value,
        ]);
    }

    public function test_it_refuses_to_exceed_the_group_capacity(): void
    {
        $group = $this->activeGroup(capacity: 1);
        $applications = [$this->clearedApplication('أول'), $this->clearedApplication('ثانٍ')];

        $this->expectException(BusinessRuleViolation::class);

        try {
            $this->action()->execute(
                actorOrganizationId: $this->organizationId,
                applicationIds: $this->idsOf($applications),
                programId: $this->programId,
                courseId: $this->courseId,
                groupId: (string) $group->getKey(),
                newGroupName: null,
                timezone: 'UTC',
                reason: 'محاولة تجاوز السعة',
                actorId: $this->actorId,
            );
        } finally {
            // لا عضوية جزئية بقيت بعد الرفض.
            $this->assertDatabaseCount('group_memberships', 0);
        }
    }

    public function test_it_skips_a_student_who_is_already_a_member_without_duplicating(): void
    {
        $group = $this->activeGroup(capacity: 5);
        $existing = $this->clearedApplication('منتسب سلفًا');
        $fresh = $this->clearedApplication('جديد');

        GroupMembership::query()->create([
            'group_id' => (string) $group->getKey(),
            'student_profile_id' => (string) $existing->student_profile_id,
            'joined_at' => now()->utc(),
            'status' => MembershipStatus::Active,
        ]);

        $result = $this->action()->execute(
            actorOrganizationId: $this->organizationId,
            applicationIds: $this->idsOf([$existing, $fresh]),
            programId: $this->programId,
            courseId: $this->courseId,
            groupId: (string) $group->getKey(),
            newGroupName: null,
            timezone: 'UTC',
            reason: 'إعادة إرسال العملية',
            actorId: $this->actorId,
        );

        $this->assertSame(1, $result->placedCount());
        $this->assertSame(1, $result->skippedExistingCount);
        $this->assertSame(2, GroupMembership::query()->count());
    }

    public function test_running_the_same_operation_twice_creates_no_duplicate_membership(): void
    {
        $group = $this->activeGroup(capacity: 5);
        $application = $this->clearedApplication('طالب مكرر');
        $ids = [(string) $application->getKey()];

        $arguments = [
            'actorOrganizationId' => $this->organizationId,
            'applicationIds' => $ids,
            'programId' => $this->programId,
            'courseId' => $this->courseId,
            'groupId' => (string) $group->getKey(),
            'newGroupName' => null,
            'timezone' => 'UTC',
            'reason' => 'ضغط مكرر على زر التنفيذ',
            'actorId' => $this->actorId,
        ];

        $this->action()->execute(...$arguments);

        // الطلب الثاني لا يجد من يصلح للتسكين لأن الطالب صار عضوًا.
        $this->expectException(BusinessRuleViolation::class);

        try {
            $this->action()->execute(...$arguments);
        } finally {
            $this->assertSame(1, GroupMembership::query()->count());
        }
    }

    public function test_it_rejects_a_student_whose_application_is_not_cleared(): void
    {
        $group = $this->activeGroup(capacity: 5);
        $submitted = $this->application('لم يُقبل بعد', RegistrationStatus::Submitted);

        $preflight = $this->action()->preflight(
            $this->organizationId,
            [(string) $submitted->getKey()],
            (string) $group->getKey(),
        );

        $this->assertSame(0, $preflight->eligibleCount());
        $this->assertCount(1, $preflight->blocked());

        $this->expectException(BusinessRuleViolation::class);
        $this->action()->execute(
            actorOrganizationId: $this->organizationId,
            applicationIds: [(string) $submitted->getKey()],
            programId: $this->programId,
            courseId: $this->courseId,
            groupId: (string) $group->getKey(),
            newGroupName: null,
            timezone: 'UTC',
            reason: 'محاولة تسكين طلب غير مقبول',
            actorId: $this->actorId,
        );
    }

    public function test_applications_of_another_organization_are_invisible(): void
    {
        $group = $this->activeGroup(capacity: 5);
        $foreign = $this->clearedApplication('طالب مؤسسة أخرى');

        DB::table('registration_applications')
            ->where('id', (string) $foreign->getKey())
            ->update(['organization_id' => $this->foreignOrganizationId()]);

        $preflight = $this->action()->preflight(
            $this->organizationId,
            [(string) $foreign->getKey()],
            (string) $group->getKey(),
        );

        $this->assertSame(0, $preflight->selectedCount());
    }

    public function test_a_failure_on_one_student_rolls_the_whole_operation_back(): void
    {
        $group = $this->activeGroup(capacity: 5);
        $good = $this->clearedApplication('سليم');
        $broken = $this->clearedApplication('قيده مؤرشف');

        /*
         * قيد مؤرشف لنفس البرنامج يجعل مسار التسجيل الرسمي يرفض هذا الطالب —
         * وهو خطأ لا يظهر إلا أثناء الحفظ، فيثبت أن الارتداد يحمي العملية لا
         * الفحص المسبق وحده.
         */
        $archived = Enrollment::query()->create([
            'organization_id' => $this->organizationId,
            'student_profile_id' => (string) $broken->student_profile_id,
            'program_id' => $this->programId,
            'status' => EnrollmentStatus::Applied,
            'applied_at' => now()->utc(),
        ]);
        $archived->delete();

        $enrollmentsBefore = Enrollment::query()->count();
        $failed = false;

        try {
            $this->action()->execute(
                actorOrganizationId: $this->organizationId,
                applicationIds: $this->idsOf([$good, $broken]),
                programId: $this->programId,
                courseId: $this->courseId,
                groupId: (string) $group->getKey(),
                newGroupName: null,
                timezone: 'UTC',
                reason: 'اختبار الذرية',
                actorId: $this->actorId,
            );
        } catch (BusinessRuleViolation) {
            $failed = true;
        }

        $this->assertTrue($failed, 'كان يجب أن تفشل العملية بسبب القيد المؤرشف.');

        // لا عضوية للطالب السليم، ولا قيد جديد، ولا تغيّرت حالة طلبه.
        $this->assertDatabaseCount('group_memberships', 0);
        $this->assertSame($enrollmentsBefore, Enrollment::query()->count());
        $this->assertSame(
            RegistrationStatus::WaitingAssignment,
            $good->refresh()->status,
        );
    }

    // ── المسار الثاني: مجموعة مسودة جديدة ───────────────────────────────

    public function test_it_creates_a_draft_group_and_places_the_students_in_it(): void
    {
        $applications = [$this->clearedApplication('أ'), $this->clearedApplication('ب')];

        $result = $this->action()->execute(
            actorOrganizationId: $this->organizationId,
            applicationIds: $this->idsOf($applications),
            programId: $this->programId,
            courseId: $this->courseId,
            groupId: null,
            newGroupName: ['ar' => 'مجموعة اللغة الإنجليزية أ', 'en' => 'English A'],
            timezone: 'Africa/Cairo',
            reason: 'إنشاء مجموعة لدفعة جديدة',
            actorId: $this->actorId,
        );

        $this->assertTrue($result->groupWasCreated);
        $this->assertTrue($result->groupIsDraft);
        $this->assertSame(2, $result->placedCount());

        $group = Group::query()->findOrFail($result->groupId);

        $this->assertSame(GroupStatus::Planning, $group->status);
        // البيانات المؤجَّلة تبقى فارغة — لا قيم وهمية.
        $this->assertNull($group->capacity);
        $this->assertNull($group->starts_on);
        $this->assertSame(0, GroupTeacher::query()->forGroup($result->groupId)->count());

        // البرنامج مربوط، والطلاب انتسبوا بانتساب معلّق.
        $this->assertDatabaseHas('group_programs', [
            'group_id' => $result->groupId,
            'program_id' => $this->programId,
        ]);
        $this->assertSame(
            2,
            GroupMembership::query()
                ->forGroup($result->groupId)
                ->where('status', MembershipStatus::Pending)
                ->count(),
        );
    }

    public function test_a_draft_group_cannot_be_activated_while_incomplete(): void
    {
        $result = $this->action()->execute(
            actorOrganizationId: $this->organizationId,
            applicationIds: [(string) $this->clearedApplication('طالب')->getKey()],
            programId: $this->programId,
            courseId: $this->courseId,
            groupId: null,
            newGroupName: ['ar' => 'مسودة ناقصة'],
            timezone: 'UTC',
            reason: 'إنشاء مسودة',
            actorId: $this->actorId,
        );

        $group = Group::query()->findOrFail($result->groupId);

        $this->expectException(BusinessRuleViolation::class);
        app(ActivateGroupAction::class)->execute(
            $group,
            $this->actorId,
            'محاولة تفعيل ناقصة',
        );
    }

    public function test_activating_a_completed_draft_promotes_pending_memberships(): void
    {
        $result = $this->action()->execute(
            actorOrganizationId: $this->organizationId,
            applicationIds: [(string) $this->clearedApplication('طالب')->getKey()],
            programId: $this->programId,
            courseId: $this->courseId,
            groupId: null,
            newGroupName: ['ar' => 'مسودة مكتملة'],
            timezone: 'UTC',
            reason: 'إنشاء مسودة',
            actorId: $this->actorId,
        );

        $group = Group::query()->findOrFail($result->groupId);
        $this->completeDraft($group, capacity: 4);

        app(ActivateGroupAction::class)->execute(
            $group->refresh(),
            $this->actorId,
            'اكتملت البيانات',
        );

        $this->assertSame(GroupStatus::Active, $group->refresh()->status);
        $this->assertSame(
            1,
            GroupMembership::query()
                ->forGroup($result->groupId)
                ->where('status', MembershipStatus::Active)
                ->count(),
        );
        $this->assertSame(
            0,
            GroupMembership::query()
                ->forGroup($result->groupId)
                ->where('status', MembershipStatus::Pending)
                ->count(),
        );
    }

    public function test_a_group_cannot_be_activated_with_capacity_below_its_members(): void
    {
        $result = $this->action()->execute(
            actorOrganizationId: $this->organizationId,
            applicationIds: $this->idsOf([
                $this->clearedApplication('أ'),
                $this->clearedApplication('ب'),
                $this->clearedApplication('ج'),
            ]),
            programId: $this->programId,
            courseId: $this->courseId,
            groupId: null,
            newGroupName: ['ar' => 'مسودة بثلاثة طلاب'],
            timezone: 'UTC',
            reason: 'إنشاء مسودة',
            actorId: $this->actorId,
        );

        $group = Group::query()->findOrFail($result->groupId);
        // سعة أقل من عدد المسكَّنين فعلًا.
        $this->completeDraft($group, capacity: 2);

        $this->expectException(BusinessRuleViolation::class);
        app(ActivateGroupAction::class)->execute(
            $group->refresh(),
            $this->actorId,
            'محاولة تفعيل بسعة ناقصة',
        );
    }

    public function test_it_requires_either_an_existing_group_or_a_new_name(): void
    {
        $this->expectException(BusinessRuleViolation::class);

        $this->action()->execute(
            actorOrganizationId: $this->organizationId,
            applicationIds: [(string) $this->clearedApplication('طالب')->getKey()],
            programId: $this->programId,
            courseId: $this->courseId,
            groupId: null,
            newGroupName: null,
            timezone: 'UTC',
            reason: 'بلا وجهة',
            actorId: $this->actorId,
        );
    }

    public function test_it_requires_a_written_reason(): void
    {
        $this->expectException(BusinessRuleViolation::class);

        $this->action()->execute(
            actorOrganizationId: $this->organizationId,
            applicationIds: [(string) $this->clearedApplication('طالب')->getKey()],
            programId: $this->programId,
            courseId: $this->courseId,
            groupId: null,
            newGroupName: ['ar' => 'مجموعة'],
            timezone: 'UTC',
            reason: '   ',
            actorId: $this->actorId,
        );
    }

    public function test_it_writes_one_audit_entry_for_the_whole_operation(): void
    {
        $group = $this->activeGroup(capacity: 5);
        $applications = [$this->clearedApplication('أ'), $this->clearedApplication('ب')];

        $this->action()->execute(
            actorOrganizationId: $this->organizationId,
            applicationIds: $this->idsOf($applications),
            programId: $this->programId,
            courseId: $this->courseId,
            groupId: (string) $group->getKey(),
            newGroupName: null,
            timezone: 'UTC',
            reason: 'توزيع الدفعة',
            actorId: $this->actorId,
        );

        $entry = DB::table('audit_log')
            ->where('action', 'enrollment.bulk_placed')
            ->where('auditable_id', (string) $group->getKey())
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame($this->actorId, $entry->actor_id);
        $this->assertSame('توزيع الدفعة', $entry->reason);

        $newValues = json_decode((string) $entry->new_values, true);
        $this->assertSame(2, $newValues['placed_count']);
        // لا بيانات شخصية ولا إجابات تسجيل في السجل العام.
        $this->assertArrayNotHasKey('full_name', $newValues);
    }

    // ── مساعدات ─────────────────────────────────────────────────────────

    private function action(): BulkAssignStudentsToGroupAction
    {
        return app(BulkAssignStudentsToGroupAction::class);
    }

    /** @param list<RegistrationApplication> $applications */
    private function idsOf(array $applications): array
    {
        return array_map(
            static fn (RegistrationApplication $application): string => (string) $application->getKey(),
            $applications,
        );
    }

    private function activeGroup(int $capacity): Group
    {
        $group = Group::query()->create([
            'organization_id' => $this->organizationId,
            'code' => 'G'.mb_substr((string) Str::ulid(), -6),
            'name' => ['ar' => 'مجموعة قائمة', 'en' => 'Existing group'],
            'capacity' => $capacity,
            'timezone' => 'UTC',
            'status' => GroupStatus::Active,
            'starts_on' => now()->toDateString(),
        ]);

        GroupProgram::query()->create([
            'group_id' => (string) $group->getKey(),
            'program_id' => $this->programId,
        ]);

        $staffProfileId = Fixtures::staffProfileId();
        Fixtures::qualifyTeacher($staffProfileId, $this->courseId);

        GroupTeacher::query()->create([
            'group_id' => (string) $group->getKey(),
            'staff_profile_id' => $staffProfileId,
            'course_id' => $this->courseId,
            'role' => 'lead',
            'assigned_from' => now()->subDay()->toDateString(),
        ]);

        return $group;
    }

    /** يستكمل مسودة بالبيانات المؤجَّلة حتى تصير قابلة للتفعيل. */
    private function completeDraft(Group $group, int $capacity): void
    {
        $staffProfileId = Fixtures::staffProfileId();
        Fixtures::qualifyTeacher($staffProfileId, $this->courseId);

        GroupTeacher::query()->create([
            'group_id' => (string) $group->getKey(),
            'staff_profile_id' => $staffProfileId,
            'course_id' => $this->courseId,
            'role' => 'lead',
            'assigned_from' => now()->toDateString(),
        ]);

        $group->capacity = $capacity;
        $group->starts_on = now()->addWeek()->toDateString();
        $group->save();
    }

    private function clearedApplication(string $name): RegistrationApplication
    {
        $userId = Fixtures::userId();

        $profile = StudentProfile::query()->create([
            'organization_id' => $this->organizationId,
            'user_id' => $userId,
            'student_code' => 'E'.mb_substr((string) Str::ulid(), -6),
            'date_of_birth' => '2010-01-01',
            'gender' => 'male',
            'country_id' => $this->countryId,
            'region_id' => $this->regionId,
            'joined_at' => now()->toDateString(),
        ]);

        return $this->application(
            $name,
            RegistrationStatus::WaitingAssignment,
            $userId,
            (string) $profile->getKey(),
        );
    }

    private function application(
        string $name,
        RegistrationStatus $status,
        ?string $userId = null,
        ?string $studentProfileId = null,
    ): RegistrationApplication {
        return RegistrationApplication::query()->create([
            'organization_id' => $this->organizationId,
            'user_id' => $userId ?? Fixtures::userId(),
            'student_profile_id' => $studentProfileId,
            'status' => $status,
            'full_name' => $name,
            'date_of_birth' => '2010-01-01',
            'gender' => 'male',
            'country_id' => $this->countryId,
            'region_id' => $this->regionId,
            'preferred_program_id' => $this->programId,
            'preferred_course_id' => $this->courseId,
        ]);
    }

    private function foreignOrganizationId(): string
    {
        $id = (string) Str::ulid();

        DB::table('organizations')->insert([
            'id' => $id,
            'name' => json_encode(['ar' => 'مؤسسة أخرى', 'en' => 'Other'], JSON_UNESCAPED_UNICODE),
            'slug' => 'other-'.strtolower(substr($id, -10)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
