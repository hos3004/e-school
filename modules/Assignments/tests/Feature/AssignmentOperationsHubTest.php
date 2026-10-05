<?php

declare(strict_types=1);

namespace Modules\Assignments\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Assignments\Application\Actions\ArchiveAssignmentAction;
use Modules\Assignments\Application\Actions\CreateAssignmentAction;
use Modules\Assignments\Application\Actions\GradeSubmissionAction;
use Modules\Assignments\Application\Actions\SubmitAssignmentAction;
use Modules\Assignments\Application\Actions\UpdateAssignmentAction;
use Modules\Assignments\Application\Queries\AssignmentAdministrationQueryService;
use Modules\Assignments\Domain\Enums\AssignmentSubmissionStatus;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Assignments\Domain\Models\AssignmentSubmission;
use Modules\Assignments\Presentation\Filament\Resources\AssignmentFilamentResource;
use Modules\Identity\Domain\Models\User;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class AssignmentOperationsHubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Fixtures::flush();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_creation_builds_target_roster_and_audit_atomically(): void
    {
        $target = $this->target(2);

        $assignment = $this->createAssignment($target);

        self::assertSame(2, $assignment->submissions()->count());
        self::assertSame(2, $assignment->submissions()->pending()->count());
        $this->assertDatabaseHas('audit_log', [
            'action' => 'assignments.created',
            'auditable_id' => (string) $assignment->getKey(),
            'reason' => 'إنشاء واجب اختبار تشغيلي',
        ]);

        $metrics = app(AssignmentAdministrationQueryService::class)->metrics($assignment);
        self::assertSame([
            'recipients' => 2,
            'pending' => 2,
            'submitted' => 0,
            'late' => 0,
            'graded' => 0,
        ], $metrics);
    }

    public function test_invalid_teacher_is_rejected_without_partial_records(): void
    {
        $target = $this->target(1);
        $unqualifiedTeacher = Fixtures::staffProfileId();

        try {
            app(CreateAssignmentAction::class)->execute(
                $this->assignmentData($target, ['staff_profile_id' => $unqualifiedTeacher]),
                $target['actor_id'],
                'محاولة إسناد غير مؤهل',
            );
            self::fail('Expected an ineligible-teacher rejection.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('assignments.teacher_not_eligible', $violation->rule);
        }

        self::assertSame(0, Assignment::query()->count());
        self::assertSame(0, AssignmentSubmission::query()->count());
        $this->assertDatabaseMissing('audit_log', ['action' => 'assignments.created']);
    }

    public function test_late_submission_applies_penalty_and_audits_both_steps(): void
    {
        $target = $this->target(1);
        $assignment = $this->createAssignment($target, [
            'assigned_at' => now()->utc()->subDays(2)->toIso8601String(),
            'due_at' => now()->utc()->subDay()->toIso8601String(),
            'late_penalty_percent' => 20,
        ]);
        $submission = $assignment->submissions()->firstOrFail();

        $submitted = app(SubmitAssignmentAction::class)->execute(
            $submission,
            ['content' => 'إجابة الطالب بعد الموعد'],
            $target['actor_id'],
        );
        $graded = app(GradeSubmissionAction::class)->execute(
            $submitted,
            ['score' => 90, 'feedback' => 'إجابة صحيحة مع تطبيق سياسة التأخير'],
            $target['actor_id'],
            'اعتماد التصحيح وخصم التأخير',
        );

        self::assertSame(AssignmentSubmissionStatus::Graded, $graded->status);
        self::assertSame(90, $graded->raw_score);
        self::assertSame(18, $graded->penalty_points);
        self::assertSame(72, $graded->score);
        $this->assertDatabaseHas('audit_log', ['action' => 'assignments.submitted', 'auditable_id' => (string) $graded->getKey()]);
        $this->assertDatabaseHas('audit_log', ['action' => 'assignments.graded', 'auditable_id' => (string) $graded->getKey()]);
    }

    public function test_strict_deadline_rejects_late_submission_without_mutation(): void
    {
        $target = $this->target(1);
        $assignment = $this->createAssignment($target, [
            'assigned_at' => now()->utc()->subDays(2)->toIso8601String(),
            'due_at' => now()->utc()->subDay()->toIso8601String(),
            'allows_late' => false,
            'late_penalty_percent' => 0,
        ]);
        $submission = $assignment->submissions()->firstOrFail();

        $this->expectException(BusinessRuleViolation::class);

        try {
            app(SubmitAssignmentAction::class)->execute(
                $submission,
                ['content' => 'تسليم مرفوض'],
                $target['actor_id'],
            );
        } finally {
            self::assertSame(AssignmentSubmissionStatus::Pending, $submission->fresh()?->status);
            $this->assertDatabaseMissing('audit_log', [
                'action' => 'assignments.submitted',
                'auditable_id' => (string) $submission->getKey(),
            ]);
        }
    }

    public function test_audience_change_and_archive_are_blocked_while_student_work_exists(): void
    {
        $target = $this->target(1);
        $assignment = $this->createAssignment($target);
        $submission = app(SubmitAssignmentAction::class)->execute(
            $assignment->submissions()->firstOrFail(),
            ['content' => 'تسليم يحتاج تصحيحًا'],
            $target['actor_id'],
        );

        try {
            app(UpdateAssignmentAction::class)->execute(
                $assignment,
                ['group_id' => null],
                $target['actor_id'],
                'محاولة تغيير الجمهور',
            );
            self::fail('Expected the audience to be locked.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('assignments.audience_locked', $violation->rule);
        }

        try {
            app(ArchiveAssignmentAction::class)->execute($assignment, $target['actor_id'], 'محاولة أرشفة مبكرة');
            self::fail('Expected ungraded submissions to block archive.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('assignments.ungraded_submissions', $violation->rule);
        }

        self::assertNotNull($submission->fresh());
        self::assertFalse($assignment->fresh()?->trashed() ?? true);
    }

    public function test_pending_only_assignment_can_be_soft_archived_with_reason(): void
    {
        $target = $this->target(1);
        $assignment = $this->createAssignment($target);

        app(ArchiveAssignmentAction::class)->execute($assignment, $target['actor_id'], 'إلغاء الواجب قبل استلام أي إجابة');

        self::assertTrue($assignment->fresh()?->trashed() ?? false);
        self::assertSame(1, AssignmentSubmission::query()->where('assignment_id', $assignment->getKey())->count());
        $this->assertDatabaseHas('audit_log', [
            'action' => 'assignments.archived',
            'reason' => 'إلغاء الواجب قبل استلام أي إجابة',
        ]);
    }

    public function test_api_enforces_permission_and_object_organization_scope(): void
    {
        $target = $this->target(1);
        $outsider = User::factory()->create(['organization_id' => $target['organization_id']]);

        $this->actingAs($outsider)
            ->postJson('/api/assignments', $this->assignmentData($target, ['reason' => 'طلب غير مصرح']))
            ->assertForbidden();

        $assignment = $this->createAssignment($target);
        $otherOrganizationId = $this->organization('other-assignment-org');
        $otherAdmin = User::factory()->create(['organization_id' => $otherOrganizationId]);
        $this->grantPermission($otherAdmin, 'assignment.manage');
        $this->grantPermission($otherAdmin, 'settings.manage');

        $this->actingAs($otherAdmin)
            ->getJson('/api/assignments/'.$assignment->getKey())
            ->assertForbidden();
    }

    public function test_filament_list_create_view_and_edit_pages_render_for_authorized_staff(): void
    {
        $target = $this->target(1);
        $assignment = $this->createAssignment($target);
        $admin = User::query()->findOrFail($target['actor_id']);

        foreach (['admin.panel.access', 'assignment.manage', 'settings.manage', 'assignment.grade'] as $permission) {
            $this->grantPermission($admin, $permission);
        }

        $this->actingAs($admin)
            ->get('/admin/assignments')
            ->assertOk()
            ->assertSeeText(__('filament-actions::create.single.label', [
                'label' => AssignmentFilamentResource::getModelLabel(),
            ]));
        $this->actingAs($admin)->get('/admin/assignments/create')->assertOk();
        $this->actingAs($admin)
            ->get('/admin/assignments/'.$assignment->getKey())
            ->assertOk()
            ->assertSeeText('لقطة التسليمات')
            ->assertDontSeeText('Submission snapshot');
        $this->actingAs($admin)->get('/admin/assignments/'.$assignment->getKey().'/edit')->assertOk();
    }

    public function test_grading_breakdown_migration_is_reversible(): void
    {
        /** @var object{up: callable(): void, down: callable(): void} $migration */
        $migration = require base_path('modules/Assignments/database/migrations/2026_08_24_230000_add_grading_breakdown_to_assignment_submissions.php');

        self::assertTrue(Schema::hasColumns('assignment_submissions', [
            'raw_score',
            'penalty_points',
        ]));

        $migration->down();

        self::assertFalse(Schema::hasColumn('assignment_submissions', 'raw_score'));
        self::assertFalse(Schema::hasColumn('assignment_submissions', 'penalty_points'));

        $migration->up();

        self::assertTrue(Schema::hasColumns('assignment_submissions', [
            'raw_score',
            'penalty_points',
        ]));
    }

    public function test_all_operational_translations_exist_in_arabic_english_and_french(): void
    {
        foreach (['ar', 'en', 'fr'] as $locale) {
            app()->setLocale($locale);

            foreach ([
                'assignments::filament.hub.overview',
                'assignments::filament.actions.grade',
                'assignments::status.assignment.open',
                'assignments::errors.teacher_not_eligible',
                'assignments::messages.created',
                'assignments::messages.yes',
                'assignments::messages.no',
            ] as $key) {
                self::assertNotSame($key, __($key), $locale.' missing '.$key);
            }
        }
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $override
     */
    private function createAssignment(array $target, array $override = []): Assignment
    {
        return app(CreateAssignmentAction::class)->execute(
            $this->assignmentData($target, $override),
            $target['actor_id'],
            'إنشاء واجب اختبار تشغيلي',
        );
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function assignmentData(array $target, array $override = []): array
    {
        return array_replace([
            'organization_id' => $target['organization_id'],
            'course_id' => $target['course_id'],
            'group_id' => $target['group_id'],
            'staff_profile_id' => $target['staff_profile_id'],
            'title' => ['ar' => 'واجب دورة العمل', 'en' => 'Operations assignment', 'fr' => 'Devoir opérationnel'],
            'instructions' => ['ar' => 'أجب بوضوح', 'en' => 'Answer clearly', 'fr' => 'Répondez clairement'],
            'assigned_at' => now()->utc()->toIso8601String(),
            'due_at' => now()->utc()->addDays(2)->toIso8601String(),
            'max_score' => 100,
            'allows_late' => true,
            'late_penalty_percent' => 10,
            'reason' => 'إنشاء واجب اختبار تشغيلي',
        ], $override);
    }

    /** @return array<string, mixed> */
    private function target(int $studentCount): array
    {
        $organizationId = Fixtures::organizationId();
        $actorId = Fixtures::userId();
        $staffProfileId = Fixtures::staffProfileId();
        $programId = (string) Str::ulid();
        $levelId = (string) Str::ulid();
        $courseId = (string) Str::ulid();
        $groupId = (string) Str::ulid();
        $now = now()->utc();

        DB::table('programs')->insert([
            'id' => $programId, 'organization_id' => $organizationId, 'code' => 'PRG-'.substr($programId, -6),
            'name' => json_encode(['ar' => 'برنامج الاختبار', 'en' => 'Test program'], JSON_UNESCAPED_UNICODE),
            'default_session_minutes' => 60, 'currency' => 'USD', 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('levels')->insert([
            'id' => $levelId, 'program_id' => $programId, 'code' => 'L1',
            'name' => json_encode(['ar' => 'المستوى الأول', 'en' => 'Level one'], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
        ]);
        DB::table('courses')->insert([
            'id' => $courseId, 'organization_id' => $organizationId, 'level_id' => $levelId,
            'code' => 'CRS-'.substr($courseId, -6),
            'name' => json_encode(['ar' => 'مقرر الاختبار', 'en' => 'Test course'], JSON_UNESCAPED_UNICODE),
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('groups')->insert([
            'id' => $groupId, 'organization_id' => $organizationId, 'code' => 'GRP-'.substr($groupId, -6),
            'name' => json_encode(['ar' => 'مجموعة الاختبار', 'en' => 'Test group'], JSON_UNESCAPED_UNICODE),
            'capacity' => max(10, $studentCount), 'timezone' => 'UTC', 'status' => 'active',
            'starts_on' => $now->toDateString(), 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('group_programs')->insert([
            'id' => (string) Str::ulid(), 'group_id' => $groupId, 'program_id' => $programId, 'created_at' => $now,
        ]);
        DB::table('teacher_courses')->insert([
            'id' => (string) Str::ulid(), 'staff_profile_id' => $staffProfileId, 'course_id' => $courseId,
            'qualified_at' => $now, 'qualified_by' => $actorId, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('group_teachers')->insert([
            'id' => (string) Str::ulid(), 'group_id' => $groupId, 'staff_profile_id' => $staffProfileId,
            'course_id' => $courseId, 'role' => 'lead', 'assigned_from' => $now->toDateString(), 'created_at' => $now,
        ]);

        foreach (range(1, $studentCount) as $index) {
            $studentProfileId = Fixtures::studentProfileId();
            DB::table('enrollments')->insert([
                'id' => (string) Str::ulid(), 'organization_id' => $organizationId,
                'student_profile_id' => $studentProfileId, 'program_id' => $programId, 'status' => 'active',
                'applied_at' => $now, 'activated_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('group_memberships')->insert([
                'id' => (string) Str::ulid(), 'group_id' => $groupId, 'student_profile_id' => $studentProfileId,
                'joined_at' => $now, 'status' => 'active', 'created_at' => $now,
            ]);
        }

        return compact('organizationId', 'actorId', 'staffProfileId', 'programId', 'courseId', 'groupId') + [
            'organization_id' => $organizationId,
            'actor_id' => $actorId,
            'staff_profile_id' => $staffProfileId,
            'program_id' => $programId,
            'course_id' => $courseId,
            'group_id' => $groupId,
        ];
    }

    private function grantPermission(User $user, string $permissionName): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => GuardName::Web->value, 'module' => 'Assignments'],
        );
        ModelHasPermission::query()->firstOrCreate([
            'permission_id' => (string) $permission->getKey(),
            'model_type' => $user->getMorphClass(),
            'model_id' => (string) $user->getAuthIdentifier(),
        ]);
        app(PermissionGateRegistrar::class)->register();
    }

    private function organization(string $slug): string
    {
        $id = (string) Str::ulid();
        DB::table('organizations')->insert([
            'id' => $id,
            'name' => json_encode(['ar' => 'مؤسسة أخرى', 'en' => 'Other organization'], JSON_UNESCAPED_UNICODE),
            'slug' => $slug,
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);

        return $id;
    }
}
