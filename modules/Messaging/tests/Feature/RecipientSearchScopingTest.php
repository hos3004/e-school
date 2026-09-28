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
