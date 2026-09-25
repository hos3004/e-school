<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Academics\Domain\Models\Course;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Audit\Domain\Models\AuditLog;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Tests\TestCase;

/**
 * بوابة دخول الأدمن الرقابية لأي حصة — منفصلة عن بوابة المعلم/الطالب،
 * تشترط صلاحية classroom.observe فقط، وتُسجَّل في سجل التدقيق.
 */
final class ConsoleSessionObserveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::create(2026, 9, 25, 12, 0, 0, 'UTC'));
        config(['console.enabled' => true, 'virtual-classroom.default' => 'null']);
        $this->seed(AccessControlSeeder::class);
        app(PermissionGateRegistrar::class)->register();
    }

    public function test_a_user_without_classroom_observe_is_forbidden(): void
    {
        [$org, $session] = $this->context();
        $actor = $this->actor($org, ['admin.panel.access', 'session.view']);

        $this->actingAs($actor)
            ->get("/manage/sessions/{$session->id}/observe?mode=announced")
            ->assertForbidden();

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_a_user_with_classroom_observe_can_reach_an_in_progress_session_announced(): void
    {
        [$org, $session] = $this->context();
        $actor = $this->actor($org, ['admin.panel.access', 'classroom.observe']);

        $response = $this->actingAs($actor)
            ->get("/manage/sessions/{$session->id}/observe?mode=announced");

        $response->assertRedirect();
        $this->assertStringStartsWith('https://virtual-classroom.test/join', (string) $response->headers->get('Location'));

        $entry = AuditLog::query()->where('action', 'virtualclassroom.observed')->sole();
        $this->assertSame($org, $entry->organization_id);
        $this->assertSame($actor->id, $entry->actor_id);
        $this->assertSame((string) $session->id, $entry->auditable_id);
        $this->assertSame('announced', $entry->new_values['mode'] ?? null);
    }

    public function test_pseudonymous_mode_is_recorded_in_the_audit_entry(): void
    {
        [$org, $session] = $this->context();
        $actor = $this->actor($org, ['admin.panel.access', 'classroom.observe']);

        $this->actingAs($actor)
            ->get("/manage/sessions/{$session->id}/observe?mode=pseudonymous")
            ->assertRedirect();

        $entry = AuditLog::query()->where('action', 'virtualclassroom.observed')->sole();
        $this->assertSame('pseudonymous', $entry->new_values['mode'] ?? null);
    }

    public function test_a_non_joinable_session_status_is_rejected_and_not_audited(): void
    {
        [$org, , $staff, $course] = $this->contextWithParts();
        $session = $this->lesson($org, $staff, $course, -300, -240, SessionStatus::Completed);
        $actor = $this->actor($org, ['admin.panel.access', 'classroom.observe']);

        $this->actingAs($actor)
            ->get("/manage/sessions/{$session->id}/observe?mode=announced")
            ->assertRedirect()
            ->assertSessionHasErrors('business_rule');

        $this->assertSame(0, AuditLog::query()->where('action', 'virtualclassroom.observed')->count());
    }

    public function test_organization_scope_is_enforced(): void
    {
        [, $session] = $this->context();
        $otherOrg = (string) Organization::factory()->create()->id;
        $actor = $this->actor($otherOrg, ['admin.panel.access', 'classroom.observe']);

        $this->actingAs($actor)
            ->get("/manage/sessions/{$session->id}/observe?mode=announced")
            ->assertNotFound();
    }

    /** @return array{string, Session} */
    private function context(): array
    {
        [$organizationId, , $staff, $course] = $this->contextWithParts();
        $session = $this->lesson($organizationId, $staff, $course, -10, 50, SessionStatus::InProgress);

        return [$organizationId, $session];
    }

    /** @return array{string, User, string, Course} */
    private function contextWithParts(): array
    {
        $org = (string) Organization::factory()->create()->id;
        $teacherUser = User::factory()->inOrganization($org)->create();
        $staff = (string) Str::ulid();
        DB::table('staff_profiles')->insert(['id' => $staff, 'organization_id' => $org,
            'user_id' => $teacherUser->id, 'staff_code' => 'O-'.Str::random(8), 'employment_type' => 'part_time',
            'created_at' => now(), 'updated_at' => now()]);
        $course = Course::factory()->create(['organization_id' => $org, 'name' => ['ar' => 'دورة مراقبة']]);

        return [$org, $teacherUser, $staff, $course];
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

    private function lesson(string $org, string $staff, Course $course, int $startsInMinutes, int $endsInMinutes,
        SessionStatus $status = SessionStatus::Scheduled): Session
    {
        $now = CarbonImmutable::now('UTC');

        return Session::query()->create(['organization_id' => $org, 'course_id' => $course->id,
            'staff_profile_id' => $staff, 'original_teacher_id' => $staff, 'session_type' => 'regular',
            'status' => $status, 'scheduled_start' => $now->addMinutes($startsInMinutes),
            'scheduled_end' => $now->addMinutes($endsInMinutes), 'title' => ['ar' => 'حصة مراقبة']]);
    }
}
