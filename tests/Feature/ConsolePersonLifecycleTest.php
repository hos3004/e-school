<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Academics\Domain\Models\Program;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Testing\Fixtures;
use Tests\TestCase;

/**
 * إجراءات دورة حياة الحساب من صفحة الملف في لوحة الإدارة.
 *
 * القاعدة المحمية هنا: لا حذف نهائي لبيانات أي شخص، والتجميد يخص برنامجًا
 * واحدًا دون بقية برامج الطالب، وكل إجراء له سبب مكتوب في سجل التدقيق.
 */
final class ConsolePersonLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $permissions = [
        'admin.panel.access', 'student.view.any', 'student.update',
        'staff.view.any', 'staff.contract.update', 'enrollment.freeze',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['console.enabled' => true]);
        $this->withoutVite();
        foreach ($this->permissions as $ability) {
            Gate::define($ability, fn (): bool => in_array($ability, $this->permissions, true));
        }
    }

    public function test_student_suspension_keeps_data_and_records_reason_then_reactivates(): void
    {
        $admin = $this->admin();
        $student = StudentProfile::query()->findOrFail(Fixtures::studentProfileId());
        $url = '/manage/students/'.$student->id;

        $this->actingAs($admin, 'web')->put($url.'/archive')->assertSessionHasErrors('reason');
        $this->assertNull($student->fresh()->deleted_at);

        $this->put($url.'/archive', ['reason' => 'توقف الطالب عن الحضور بطلب ولي الأمر'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertNotNull(StudentProfile::withTrashed()->findOrFail($student->id)->deleted_at);
        $this->assertDatabaseHas('student_profiles', ['id' => $student->id]);
        $this->assertDatabaseHas('audit_log', [
            'action' => 'students.archived', 'auditable_id' => $student->id, 'actor_id' => $admin->id,
        ]);

        $this->put($url.'/restore', ['reason' => 'عاد الطالب للدراسة'])->assertRedirect();
        $this->assertNull(StudentProfile::withTrashed()->findOrFail($student->id)->deleted_at);
        $this->assertDatabaseHas('audit_log', [
            'action' => 'students.restored', 'auditable_id' => $student->id, 'actor_id' => $admin->id,
        ]);
    }

    public function test_teacher_termination_records_reason_without_deleting_the_account(): void
    {
        $admin = $this->admin();
        $teacher = StaffProfile::query()->findOrFail(Fixtures::staffProfileId());

        $this->actingAs($admin, 'web')
            ->put('/manage/teachers/'.$teacher->id.'/terminate')->assertSessionHasErrors('reason');

        $this->put('/manage/teachers/'.$teacher->id.'/terminate', ['reason' => 'إنهاء الخدمة بطلب المعلم'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertNotNull($teacher->fresh()->terminated_at);
        $this->assertDatabaseHas('staff_profiles', ['id' => $teacher->id]);
        $this->assertDatabaseHas('audit_log', [
            'action' => 'staff.profile_terminated', 'auditable_id' => $teacher->id, 'actor_id' => $admin->id,
        ]);
    }

    public function test_freezing_one_program_leaves_the_students_other_programs_active(): void
    {
        $admin = $this->admin();
        $student = StudentProfile::query()->findOrFail(Fixtures::studentProfileId());
        $frozen = $this->enrollment($student, 'برنامج القرآن');
        $untouched = $this->enrollment($student, 'برنامج العلوم الشرعية');

        $this->actingAs($admin, 'web')
            ->put('/manage/enrollments/'.$frozen->id.'/freeze')->assertSessionHasErrors('reason');

        $this->put('/manage/enrollments/'.$frozen->id.'/freeze', ['reason' => 'قرار تأديبي بعد ثلاث مخالفات محتسبة'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(EnrollmentStatus::Frozen, $frozen->fresh()->status);
        $this->assertSame('manual', $frozen->fresh()->freeze_type);
        $this->assertSame(EnrollmentStatus::Active, $untouched->fresh()->status);
        $this->assertDatabaseHas('enrollment_status_history', [
            'enrollment_id' => $frozen->id, 'to_status' => 'frozen',
        ]);
        $this->assertDatabaseHas('audit_log', [
            'action' => 'enrollments.status_changed', 'auditable_id' => $frozen->id,
        ]);
    }

    public function test_profile_exposes_only_permitted_actions_and_freezable_programs(): void
    {
        $admin = $this->admin();
        $student = StudentProfile::query()->findOrFail(Fixtures::studentProfileId());
        $active = $this->enrollment($student, 'برنامج القرآن');
        $completed = $this->enrollment($student, 'برنامج منتهٍ');
        DB::table('enrollments')->where('id', $completed->id)->update(['status' => EnrollmentStatus::Completed->value]);

        $this->actingAs($admin, 'web')->get('/manage/students/'.$student->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lifecycle.terminateUrl', null)
                ->where('lifecycle.restoreUrl', null)
                ->has('lifecycle.enrollments', 1)
                ->where('lifecycle.enrollments.0.id', $active->id));

        $this->permissions = ['admin.panel.access', 'student.view.any'];
        $this->get('/manage/students/'.$student->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lifecycle.archiveUrl', null)
                ->has('lifecycle.enrollments', 0));
        $this->put('/manage/students/'.$student->id.'/archive', ['reason' => 'محاولة بلا صلاحية'])->assertForbidden();
        $this->put('/manage/enrollments/'.$active->id.'/freeze', ['reason' => 'محاولة بلا صلاحية'])->assertForbidden();
        $this->assertNull($student->fresh()->deleted_at);
        $this->assertSame(EnrollmentStatus::Active, $active->fresh()->status);
    }

    public function test_actions_never_reach_another_organizations_records(): void
    {
        $admin = $this->admin();
        $foreignOrganization = Organization::factory()->create();
        $foreignUser = User::factory()->inOrganization((string) $foreignOrganization->id)->create();
        $foreignStudent = StudentProfile::factory()->create([
            'organization_id' => $foreignOrganization->id, 'user_id' => $foreignUser->id,
        ]);

        $this->actingAs($admin, 'web')
            ->put('/manage/students/'.$foreignStudent->id.'/archive', ['reason' => 'مؤسسة أخرى'])
            ->assertNotFound();
        $this->assertNull($foreignStudent->fresh()->deleted_at);
    }

    private function admin(): User
    {
        return User::factory()->inOrganization(Fixtures::organizationId())->create();
    }

    private function enrollment(StudentProfile $student, string $programName): Enrollment
    {
        $program = Program::factory()->create([
            'organization_id' => (string) $student->organization_id,
            'name' => ['ar' => $programName, 'en' => $programName],
        ]);

        return Enrollment::query()->create([
            'organization_id' => (string) $student->organization_id,
            'student_profile_id' => (string) $student->id,
            'program_id' => (string) $program->id,
            'status' => EnrollmentStatus::Active,
            'applied_at' => now()->subMonth()->utc(),
            'activated_at' => now()->subMonth()->utc(),
        ]);
    }
}
