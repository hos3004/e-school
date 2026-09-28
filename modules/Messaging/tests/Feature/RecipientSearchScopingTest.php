<?php

declare(strict_types=1);

namespace Modules\Messaging\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Identity\Domain\Models\User;
use Shared\Testing\Fixtures;
use Tests\TestCase;

/**
 * قبل هذا التغيير كان بحث المستلمين يرجّع كل حسابات المؤسسة النشطة بلا أي
 * قيد — أي معلم كان يقدر يبدأ محادثة مع أي طالب في المدرسة، مش طلابه فقط.
 */
final class RecipientSearchScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Fixtures::flush();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_teacher_search_only_returns_their_own_students(): void
    {
        $organizationId = Fixtures::organizationId();
        $teacher = User::factory()->inOrganization($organizationId)->create();
        $staffProfileId = Fixtures::staffProfileForUser($teacher->id);
        $this->grantPermission($teacher, 'message.send');

        $myStudent = User::factory()->inOrganization($organizationId)->create(['name' => 'Needle Mine']);
        $otherStudent = User::factory()->inOrganization($organizationId)->create(['name' => 'Needle Other']);
        $myStudentProfileId = Fixtures::studentProfileForUser($myStudent->id);
        Fixtures::studentProfileForUser($otherStudent->id);
        $courseId = Fixtures::courseId();

        $this->insertIndividualSchedule($organizationId, $staffProfileId, $myStudentProfileId, $courseId);

        $response = $this->actingAs($teacher)
            ->getJson('/api/messaging/recipients?q=Needle')
            ->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $myStudent->id);
    }

    public function test_teacher_with_no_students_gets_no_results_even_with_a_name_match(): void
    {
        $organizationId = Fixtures::organizationId();
        $teacher = User::factory()->inOrganization($organizationId)->create();
        Fixtures::staffProfileForUser($teacher->id);
        $this->grantPermission($teacher, 'message.send');

        User::factory()->inOrganization($organizationId)->create(['name' => 'Needle Stranger']);

        $this->actingAs($teacher)
            ->getJson('/api/messaging/recipients?q=Needle')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_moderator_search_is_not_restricted(): void
    {
        $organizationId = Fixtures::organizationId();
        $moderator = User::factory()->inOrganization($organizationId)->create();
        $this->grantPermission($moderator, 'message.send');
        $this->grantPermission($moderator, 'message.moderate');

        User::factory()->inOrganization($organizationId)->create(['name' => 'Needle Anyone']);

        $this->actingAs($moderator)
            ->getJson('/api/messaging/recipients?q=Needle')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * الإصلاح الأول كان يقيّد البحث فقط — معلم يعرف معرّف طالب غير طالبه
     * (بدون المرور بالبحث) كان لسه يقدر يبدأ محادثة معه مباشرة عبر هذا الـ
     * endpoint. هذا الاختبار يثبت أن الإنشاء نفسه مقيَّد الآن، لا الظهور في
     * نتائج البحث فقط.
     */
    public function test_teacher_cannot_start_a_direct_conversation_with_a_non_student_by_id(): void
    {
        $organizationId = Fixtures::organizationId();
        $teacher = User::factory()->inOrganization($organizationId)->create();
        Fixtures::staffProfileForUser($teacher->id);
        $this->grantPermission($teacher, 'message.send');

        $stranger = User::factory()->inOrganization($organizationId)->create();

        $this->actingAs($teacher)->postJson('/api/messaging/direct-conversations', [
            'recipient_user_id' => (string) $stranger->id,
            'subject' => 'محاولة تجاوز',
            'body' => 'مرحبًا',
        ])->assertUnprocessable();
    }

    /**
     * نفس الفحص، لكن عبر endpoint إنشاء المحادثات العام (يشمل الجماعية) —
     * إدراج طالب مش تابع للمعلم ضمن مشاركين آخرين مسموحين لازم يُرفض بالكامل.
     */
    public function test_teacher_cannot_include_a_non_student_in_a_group_conversation(): void
    {
        $organizationId = Fixtures::organizationId();
        $teacher = User::factory()->inOrganization($organizationId)->create();
        $staffProfileId = Fixtures::staffProfileForUser($teacher->id);
        $this->grantPermission($teacher, 'message.send');

        $myStudent = User::factory()->inOrganization($organizationId)->create();
        $myStudentProfileId = Fixtures::studentProfileForUser($myStudent->id);
        $courseId = Fixtures::courseId();
        $this->insertIndividualSchedule($organizationId, $staffProfileId, $myStudentProfileId, $courseId);

        $stranger = User::factory()->inOrganization($organizationId)->create();

        $this->actingAs($teacher)->postJson('/api/conversations', [
            'type' => 'group',
            'subject' => 'مجموعة',
            'participant_user_ids' => [(string) $myStudent->id, (string) $stranger->id],
        ])->assertUnprocessable();
    }

    public function test_teacher_can_still_start_a_conversation_with_their_own_student(): void
    {
        $organizationId = Fixtures::organizationId();
        $teacher = User::factory()->inOrganization($organizationId)->create();
        $staffProfileId = Fixtures::staffProfileForUser($teacher->id);
        $this->grantPermission($teacher, 'message.send');

        $myStudent = User::factory()->inOrganization($organizationId)->create();
        $myStudentProfileId = Fixtures::studentProfileForUser($myStudent->id);
        $courseId = Fixtures::courseId();
        $this->insertIndividualSchedule($organizationId, $staffProfileId, $myStudentProfileId, $courseId);

        $this->actingAs($teacher)->postJson('/api/messaging/direct-conversations', [
            'recipient_user_id' => (string) $myStudent->id,
            'subject' => 'متابعة',
            'body' => 'أهلًا',
        ])->assertCreated();
    }

    public function test_student_search_returns_their_teacher_and_classmates_and_classmates_guardian(): void
    {
        $organizationId = Fixtures::organizationId();
        $me = User::factory()->inOrganization($organizationId)->create();
        $myStudentProfileId = Fixtures::studentProfileForUser($me->id);
        $this->grantPermission($me, 'message.send');

        $teacher = User::factory()->inOrganization($organizationId)->create(['name' => 'Needle Teacher']);
        $staffProfileId = Fixtures::staffProfileForUser($teacher->id);

        $classmate = User::factory()->inOrganization($organizationId)->create(['name' => 'Needle Classmate']);
        $classmateProfileId = Fixtures::studentProfileForUser($classmate->id);

        $classmateGuardian = User::factory()->inOrganization($organizationId)->create(['name' => 'Needle Guardian']);
        $guardianProfileId = $this->insertGuardianProfile($organizationId, $classmateGuardian->id);

        $stranger = User::factory()->inOrganization($organizationId)->create(['name' => 'Needle Stranger']);

        $groupId = $this->insertGroup($organizationId);
        $this->insertGroupTeacher($organizationId, $groupId, $staffProfileId);
        $this->insertGroupMembership($groupId, $myStudentProfileId);
        $this->insertGroupMembership($groupId, $classmateProfileId);
        $this->insertGuardianLink($organizationId, $guardianProfileId, $classmateProfileId);

        $response = $this->actingAs($me)
            ->getJson('/api/messaging/recipients?q=Needle')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        expect($ids)->toContain((string) $teacher->id)
            ->toContain((string) $classmate->id)
            ->toContain((string) $classmateGuardian->id)
            ->not->toContain((string) $stranger->id);
    }

    public function test_student_cannot_start_a_direct_conversation_with_a_stranger_by_id(): void
    {
        $organizationId = Fixtures::organizationId();
        $me = User::factory()->inOrganization($organizationId)->create();
        Fixtures::studentProfileForUser($me->id);
        $this->grantPermission($me, 'message.send');

        $stranger = User::factory()->inOrganization($organizationId)->create();

        $this->actingAs($me)->postJson('/api/messaging/direct-conversations', [
            'recipient_user_id' => (string) $stranger->id,
            'subject' => 'محاولة تجاوز',
            'body' => 'مرحبًا',
        ])->assertUnprocessable();
    }

    public function test_student_on_an_individual_schedule_reaches_their_teacher_but_not_another_teachers_student(): void
    {
        $organizationId = Fixtures::organizationId();
        $me = User::factory()->inOrganization($organizationId)->create();
        $myStudentProfileId = Fixtures::studentProfileForUser($me->id);
        $this->grantPermission($me, 'message.send');

        $teacher = User::factory()->inOrganization($organizationId)->create(['name' => 'Needle Teacher']);
        $staffProfileId = Fixtures::staffProfileForUser($teacher->id);
        $courseId = Fixtures::courseId();
        $this->insertIndividualSchedule($organizationId, $staffProfileId, $myStudentProfileId, $courseId);

        // طالب آخر عند نفس المعلم لكن بلا مجموعة مشتركة — مش "زميل" فعليًا.
        $otherTeachersStudent = User::factory()->inOrganization($organizationId)->create(['name' => 'Needle Other']);
        $otherStudentProfileId = Fixtures::studentProfileForUser($otherTeachersStudent->id);
        $this->insertIndividualSchedule($organizationId, $staffProfileId, $otherStudentProfileId, $courseId);

        $response = $this->actingAs($me)
            ->getJson('/api/messaging/recipients?q=Needle')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        expect($ids)->toContain((string) $teacher->id)
            ->not->toContain((string) $otherTeachersStudent->id);
    }

    public function test_guardian_search_returns_only_their_verified_childs_teacher(): void
    {
        $organizationId = Fixtures::organizationId();
        $me = User::factory()->inOrganization($organizationId)->create();
        $guardianProfileId = $this->insertGuardianProfile($organizationId, $me->id);
        $this->grantPermission($me, 'message.send');

        $child = User::factory()->inOrganization($organizationId)->create();
        $childProfileId = Fixtures::studentProfileForUser($child->id);
        $this->insertGuardianLink($organizationId, $guardianProfileId, $childProfileId);

        $teacher = User::factory()->inOrganization($organizationId)->create(['name' => 'Needle Teacher']);
        $staffProfileId = Fixtures::staffProfileForUser($teacher->id);
        $courseId = Fixtures::courseId();
        $this->insertIndividualSchedule($organizationId, $staffProfileId, $childProfileId, $courseId);

        // طالب آخر (ليس ابن ولي الأمر هذا) عند نفس المعلم — لا يظهر.
        $unrelatedStudent = User::factory()->inOrganization($organizationId)->create(['name' => 'Needle Unrelated']);
        $unrelatedProfileId = Fixtures::studentProfileForUser($unrelatedStudent->id);
        $this->insertIndividualSchedule($organizationId, $staffProfileId, $unrelatedProfileId, $courseId);

        $response = $this->actingAs($me)
            ->getJson('/api/messaging/recipients?q=Needle')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        expect($ids)->toContain((string) $teacher->id)
            ->not->toContain((string) $unrelatedStudent->id);
    }

    public function test_guardian_with_an_unverified_link_reaches_no_one(): void
    {
        $organizationId = Fixtures::organizationId();
        $me = User::factory()->inOrganization($organizationId)->create();
        $guardianProfileId = $this->insertGuardianProfile($organizationId, $me->id);
        $this->grantPermission($me, 'message.send');

        $child = User::factory()->inOrganization($organizationId)->create();
        $childProfileId = Fixtures::studentProfileForUser($child->id);
        $this->insertGuardianLink($organizationId, $guardianProfileId, $childProfileId, verified: false);

        $teacher = User::factory()->inOrganization($organizationId)->create(['name' => 'Needle Teacher']);
        $staffProfileId = Fixtures::staffProfileForUser($teacher->id);
        $courseId = Fixtures::courseId();
        $this->insertIndividualSchedule($organizationId, $staffProfileId, $childProfileId, $courseId);

        $this->actingAs($me)
            ->getJson('/api/messaging/recipients?q=Needle')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_guardian_cannot_start_a_direct_conversation_with_a_stranger_by_id(): void
    {
        $organizationId = Fixtures::organizationId();
        $me = User::factory()->inOrganization($organizationId)->create();
        $this->insertGuardianProfile($organizationId, (string) $me->id);
        $this->grantPermission($me, 'message.send');

        $stranger = User::factory()->inOrganization($organizationId)->create();

        $this->actingAs($me)->postJson('/api/messaging/direct-conversations', [
            'recipient_user_id' => (string) $stranger->id,
            'subject' => 'محاولة تجاوز',
            'body' => 'مرحبًا',
        ])->assertUnprocessable();
    }

    private function insertGuardianProfile(string $organizationId, string $userId): string
    {
        $id = (string) Str::ulid();
        DB::table('guardian_profiles')->insert([
            'id' => $id,
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'guardian_code' => 'G'.strtoupper(substr($id, -8)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function insertGroup(string $organizationId): string
    {
        $groupId = (string) Str::ulid();
        DB::table('groups')->insert([
            'id' => $groupId,
            'organization_id' => $organizationId,
            'code' => 'SCOPE-'.strtoupper(substr($groupId, -8)),
            'name' => json_encode(['ar' => 'مجموعة الاختبار', 'en' => 'Test group'], JSON_UNESCAPED_UNICODE),
            'capacity' => 10,
            'timezone' => 'UTC',
            'status' => 'active',
            'starts_on' => CarbonImmutable::now('UTC')->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $groupId;
    }

    private function insertGroupTeacher(string $organizationId, string $groupId, string $staffProfileId): void
    {
        DB::table('group_teachers')->insert([
            'id' => (string) Str::ulid(),
            'group_id' => $groupId,
            'staff_profile_id' => $staffProfileId,
            'course_id' => null,
            'role' => 'lead',
            'assigned_from' => CarbonImmutable::now('UTC')->toDateString(),
            'assigned_to' => null,
            'created_at' => now(),
        ]);
    }

    private function insertGroupMembership(string $groupId, string $studentProfileId): void
    {
        DB::table('group_memberships')->insert([
            'id' => (string) Str::ulid(),
            'group_id' => $groupId,
            'student_profile_id' => $studentProfileId,
            'joined_at' => now(),
            'left_at' => null,
            'status' => 'active',
            'created_at' => now(),
        ]);
    }

    private function insertGuardianLink(
        string $organizationId,
        string $guardianProfileId,
        string $studentProfileId,
        bool $verified = true,
    ): void {
        DB::table('guardian_links')->insert([
            'id' => (string) Str::ulid(),
            'guardian_profile_id' => $guardianProfileId,
            'student_profile_id' => $studentProfileId,
            'relationship' => 'parent',
            'is_primary' => true,
            'can_act_for' => true,
            'visible_sections' => json_encode([], JSON_THROW_ON_ERROR),
            'verified_at' => $verified ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertIndividualSchedule(
        string $organizationId,
        string $staffProfileId,
        string $studentProfileId,
        string $courseId,
    ): void {
        DB::table('schedules')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'group_id' => null,
            'student_profile_id' => $studentProfileId,
            'course_id' => $courseId,
            'staff_profile_id' => $staffProfileId,
            'session_type' => 'individual',
            'rrule' => 'FREQ=WEEKLY',
            'start_time' => '09:00:00',
            'duration_minutes' => 30,
            'timezone' => 'UTC',
            'starts_on' => CarbonImmutable::now('UTC')->toDateString(),
            'materialized_until' => CarbonImmutable::now('UTC')->addDays(30)->toDateString(),
            'is_active' => true,
            'created_by' => Fixtures::userId(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantPermission(User $user, string $permissionName): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => GuardName::Web->value, 'module' => 'Messaging'],
        );

        ModelHasPermission::query()->create([
            'permission_id' => (string) $permission->getKey(),
            'model_type' => $user->getMorphClass(),
            'model_id' => (string) $user->getAuthIdentifier(),
        ]);

        app(PermissionGateRegistrar::class)->register();
    }
}
