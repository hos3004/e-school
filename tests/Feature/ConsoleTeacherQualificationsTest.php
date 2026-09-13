<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Groups\Domain\Enums\GroupTeacherRole;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupTeacher;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Models\StaffProfile;
use Shared\Testing\Fixtures;
use Tests\TestCase;

/**
 * إدارة الكورسات المعتمدة للمعلم من صفحة ملفه في الكونسول.
 *
 * المحروس: التأهيل قابل للتصحيح بعد إنشاء الملف لا عند إنشائه فقط، والمعلم
 * المعتمد حديثًا يظهر فعلًا في قوائم إسناد الكورس، والسحب موثق ولا يكسر إسنادًا
 * نشطًا، ولا شيء من ذلك متاح بلا صلاحية تعديل عقود الموظفين.
 */
final class ConsoleTeacherQualificationsTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $permissions = [
        'admin.panel.access', 'staff.view.any', 'staff.contract.update',
        'course.manage', 'group.view', 'group.manage',
    ];

    private string $organizationId;

    private string $courseId;

    private string $staffProfileId;

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
        $this->staffProfileId = Fixtures::staffProfileId();
        // Fixtures يكتب employment_type بقيمة ContractBasis لا يقبلها الـEnum،
        // فتُصحَّح هنا لأن صفحة الملف تقرأ الخاصية فعلًا.
        DB::table('staff_profiles')->where('id', $this->staffProfileId)
            ->update(['employment_type' => EmploymentType::Contractor->value]);
    }

    public function test_profile_offers_every_unheld_course_and_lists_the_held_ones(): void
    {
        Fixtures::qualifyTeacher($this->staffProfileId, $this->courseId);
        $other = Fixtures::courseId();

        $this->actingAs($this->admin(), 'web')
            ->get('/manage/teachers/'.$this->staffProfileId)->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('qualifications.current', 1)
                ->where('qualifications.current.0.id', $this->courseId)
                ->has('qualifications.availableCourses', 1)
                ->where('qualifications.availableCourses.0.value', $other)
                ->where('qualifications.assignUrl', url(
                    '/manage/teachers/'.$this->staffProfileId.'/qualifications',
                )));
    }

    public function test_approving_a_course_makes_the_teacher_selectable_for_it(): void
    {
        $admin = $this->admin();
        $url = '/manage/teachers/'.$this->staffProfileId.'/qualifications';

        $this->actingAs($admin, 'web')->get('/manage/courses?tab=groups')->assertOk()
            ->assertInertia(fn ($page) => $page->has('teacherOptions.'.$this->courseId, 0));

        $this->post($url, ['course_ids' => [$this->courseId]])->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('teacher_courses', 0);

        $this->post($url, [
            'course_ids' => [$this->courseId],
            'reason' => 'اعتماد المعلمة لتدريس الكورس الجديد بعد مراجعة تأهيلها',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('teacher_courses', [
            'staff_profile_id' => $this->staffProfileId,
            'course_id' => $this->courseId,
            'revoked_at' => null,
        ]);
        $this->assertTrue(DB::table('audit_log')
            ->where('action', 'staff.qualifications_assigned')
            ->where('auditable_id', $this->staffProfileId)->exists());

        $this->get('/manage/courses?tab=groups')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('teacherOptions.'.$this->courseId, 1)
                ->where('teacherOptions.'.$this->courseId.'.0.id', $this->staffProfileId));
    }

    public function test_revoking_needs_a_reason_and_keeps_the_record(): void
    {
        Fixtures::qualifyTeacher($this->staffProfileId, $this->courseId);
        $url = '/manage/teachers/'.$this->staffProfileId.'/qualifications';

        $this->actingAs($this->admin(), 'web')
            ->delete($url, ['course_id' => $this->courseId])->assertSessionHasErrors('reason');
        $this->assertNull(DB::table('teacher_courses')->value('revoked_at'));

        $this->delete($url, [
            'course_id' => $this->courseId,
            'reason' => 'لم تعد المعلمة تدرّس هذا الكورس هذا الفصل',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1, DB::table('teacher_courses')->count());
        $this->assertNotNull(DB::table('teacher_courses')->value('revoked_at'));

        $this->get('/manage/courses?tab=groups')->assertOk()
            ->assertInertia(fn ($page) => $page->has('teacherOptions.'.$this->courseId, 0));
    }

    public function test_revoking_is_refused_while_an_active_group_assignment_exists(): void
    {
        Fixtures::qualifyTeacher($this->staffProfileId, $this->courseId);
        $group = Group::query()->create([
            'organization_id' => $this->organizationId,
            'code' => 'TQ-GRP-1',
            'name' => ['ar' => 'مجموعة الاختبار'],
            'status' => 'active',
            'timezone' => 'UTC',
            'capacity' => 10,
            'starts_on' => now()->subMonth()->toDateString(),
        ]);
        GroupTeacher::query()->create([
            'group_id' => (string) $group->id,
            'staff_profile_id' => $this->staffProfileId,
            'course_id' => $this->courseId,
            'role' => GroupTeacherRole::Lead,
            'assigned_from' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($this->admin(), 'web')
            ->delete('/manage/teachers/'.$this->staffProfileId.'/qualifications', [
                'course_id' => $this->courseId,
                'reason' => 'محاولة سحب اعتماد بينما الإسناد قائم',
            ])->assertSessionHasErrors('business_rule');

        $this->assertNull(DB::table('teacher_courses')->value('revoked_at'));
    }

    public function test_nothing_is_offered_or_accepted_without_the_contract_permission(): void
    {
        $admin = $this->admin();
        Fixtures::qualifyTeacher($this->staffProfileId, $this->courseId);
        $this->permissions = ['admin.panel.access', 'staff.view.any'];

        $this->actingAs($admin, 'web')->get('/manage/teachers/'.$this->staffProfileId)->assertOk()
            ->assertInertia(fn ($page) => $page->where('qualifications', null));

        $this->post('/manage/teachers/'.$this->staffProfileId.'/qualifications', [
            'course_ids' => [$this->courseId], 'reason' => 'بلا صلاحية',
        ])->assertForbidden();
        $this->delete('/manage/teachers/'.$this->staffProfileId.'/qualifications', [
            'course_id' => $this->courseId, 'reason' => 'بلا صلاحية',
        ])->assertForbidden();

        $this->assertNull(DB::table('teacher_courses')->value('revoked_at'));
    }

    public function test_a_teacher_from_another_organization_is_not_reachable(): void
    {
        $admin = $this->admin();
        $foreign = StaffProfile::query()->create([
            'organization_id' => (string) Organization::factory()->create()->id,
            'user_id' => (string) User::factory()->create()->id,
            'staff_code' => 'TQ-FOREIGN',
            'employment_type' => EmploymentType::Contractor,
        ]);

        $this->actingAs($admin, 'web')
            ->post('/manage/teachers/'.$foreign->id.'/qualifications', [
                'course_ids' => [$this->courseId], 'reason' => 'مؤسسة أخرى',
            ])->assertNotFound();

        $this->assertDatabaseCount('teacher_courses', 0);
    }

    private function admin(): User
    {
        return User::factory()->inOrganization($this->organizationId)->create();
    }
}
