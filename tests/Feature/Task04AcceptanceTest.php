<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Assignments\Domain\Enums\AssignmentSubmissionStatus;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Assignments\Domain\Models\AssignmentSubmission;
use Modules\Groups\Domain\Models\Group;
use Modules\Identity\Domain\Contracts\PhonePasswordResetOtpDelivery;
use Modules\Identity\Domain\Models\User;
use Modules\Integrations\Domain\Contracts\ChannelGateway;
use Modules\Integrations\Domain\ValueObjects\GatewayMessage;
use Modules\Integrations\Domain\ValueObjects\GatewayResult;
use Modules\Messaging\Application\Actions\AddWallCommentAction;
use Modules\Messaging\Application\Actions\CreateConversationAction;
use Modules\Messaging\Application\Actions\PublishWallPostAction;
use Modules\Messaging\Application\Actions\SendMessageAction;
use Modules\Messaging\Domain\Enums\ConversationType;
use Modules\Messaging\Domain\Enums\ParticipantRole;
use Modules\Messaging\Domain\Models\ConversationParticipant;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Organization\Domain\Models\Organization;
use Modules\Sessions\Domain\Events\SessionScheduled;
use Shared\Support\BusinessRuleViolation;
use Tests\TestCase;

final class Task04AcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_operational_communications_and_assignments_journey_is_tenant_and_audience_safe(): void
    {
        $organization = Organization::factory()->create();
        $foreignOrganization = Organization::factory()->create();
        $organizationId = (string) $organization->getKey();

        $teacher = $this->user($organizationId, 'teacher');
        $student = $this->user($organizationId, 'student');
        $guardian = $this->user($organizationId, 'guardian');
        $outsider = $this->user((string) $foreignOrganization->getKey(), 'outsider');

        foreach (['message.send', 'class_wall.post', 'assignment.manage', 'assignment.grade'] as $permission) {
            $this->grant($teacher, $permission);
        }
        foreach (['message.send', 'assignment.submit'] as $permission) {
            $this->grant($student, $permission);
        }
        $this->grant($guardian, 'guardian.view');
        $this->grant($outsider, 'assignment.submit');

        $studentProfileId = $this->studentProfile($organizationId, (string) $student->getKey());
        $staffProfileId = $this->staffProfile($organizationId, (string) $teacher->getKey());
        $guardianProfileId = $this->guardianProfile($organizationId, (string) $guardian->getKey());

        DB::table('guardian_links')->insert([
            'id' => (string) Str::ulid(),
            'guardian_profile_id' => $guardianProfileId,
            'student_profile_id' => $studentProfileId,
            'relationship' => 'father',
            'is_primary' => true,
            'can_act_for' => true,
            'verified_at' => now()->utc(),
            'created_at' => now()->utc(),
        ]);

        $program = Program::factory()->create(['organization_id' => $organizationId]);
        $level = Level::factory()->create(['program_id' => (string) $program->getKey()]);
        $course = Course::factory()->create([
            'organization_id' => $organizationId,
            'level_id' => (string) $level->getKey(),
        ]);
        $group = Group::factory()->active()->create(['organization_id' => $organizationId]);
        $groupId = (string) $group->getKey();

        DB::table('group_programs')->insert([
            'id' => (string) Str::ulid(),
            'group_id' => $groupId,
            'program_id' => (string) $program->getKey(),
            'created_at' => now()->utc(),
        ]);
        DB::table('group_teachers')->insert([
            'id' => (string) Str::ulid(),
            'group_id' => $groupId,
            'staff_profile_id' => $staffProfileId,
            'course_id' => (string) $course->getKey(),
            'role' => 'primary',
            'assigned_from' => now()->subDay()->toDateString(),
            'created_at' => now()->utc(),
        ]);
        DB::table('group_memberships')->insert([
            'id' => (string) Str::ulid(),
            'group_id' => $groupId,
            'student_profile_id' => $studentProfileId,
            'joined_at' => now()->subDay()->utc(),
            'status' => 'active',
            'created_at' => now()->utc(),
        ]);
        $enrollmentId = (string) Str::ulid();
        DB::table('enrollments')->insert([
            'id' => $enrollmentId,
            'organization_id' => $organizationId,
            'student_profile_id' => $studentProfileId,
            'program_id' => (string) $program->getKey(),
            'status' => 'active',
            'applied_at' => now()->subDays(2)->utc(),
            'activated_at' => now()->subDay()->utc(),
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);

        Event::dispatch(new SessionScheduled(
            sessionId: (string) Str::ulid(),
            organizationId: $organizationId,
            courseId: (string) $course->getKey(),
            staffProfileId: $staffProfileId,
            scheduledStart: now()->addHours(2)->utc()->toIso8601String(),
            scheduledEnd: now()->addHours(3)->utc()->toIso8601String(),
            groupId: $groupId,
        ));

        $notifiedUsers = NotificationOutbox::query()
            ->where('organization_id', $organizationId)
            ->where('event_name', 'session.scheduled')
            ->pluck('user_id')
            ->unique()
            ->all();
        self::assertContains((string) $teacher->getKey(), $notifiedUsers);
        self::assertContains((string) $student->getKey(), $notifiedUsers);
        self::assertContains((string) $guardian->getKey(), $notifiedUsers);
        self::assertNotContains((string) $outsider->getKey(), $notifiedUsers);

        $gateway = new class implements ChannelGateway
        {
            public ?GatewayMessage $message = null;

            public function send(GatewayMessage $message): GatewayResult
            {
                $this->message = $message;

                return GatewayResult::accepted(['external_message_id' => 'wa_test']);
            }
        };
        $this->app->instance(ChannelGateway::class, $gateway);
        app(PhonePasswordResetOtpDelivery::class)->deliver(
            userId: (string) $student->getKey(),
            organizationId: $organizationId,
            phone: '+966500000000',
            otp: '123456',
            expiresAt: CarbonImmutable::now('UTC')->addMinutes(15),
        );
        self::assertSame('whatsapp', $gateway->message?->channel);
        self::assertSame($organizationId, $gateway->message?->organizationId);
        self::assertSame(['123456', $gateway->message?->payload['template_parameters'][1]], $gateway->message?->payload['template_parameters']);

        $conversation = app(CreateConversationAction::class)->execute(
            organizationId: $organizationId,
            creatorUserId: (string) $student->getKey(),
            type: ConversationType::Direct,
            subject: 'Student teacher question',
            participantUserIds: [(string) $teacher->getKey()],
        );
        app(SendMessageAction::class)->execute(
            conversation: $conversation,
            senderUserId: (string) $student->getKey(),
            body: 'Question',
        );
        ConversationParticipant::query()->create([
            'organization_id' => $organizationId,
            'conversation_id' => (string) $conversation->getKey(),
            'user_id' => (string) $guardian->getKey(),
            'role' => ParticipantRole::Member->value,
            'joined_at' => now()->utc(),
        ]);

        $this->actingAs($guardian)
            ->getJson('/api/messaging/conversations/'.$conversation->getKey())
            ->assertForbidden();
        $this->actingAs($student)
            ->getJson('/api/messaging/conversations/'.$conversation->getKey())
            ->assertOk();

        try {
            app(CreateConversationAction::class)->execute(
                organizationId: $organizationId,
                creatorUserId: (string) $student->getKey(),
                type: ConversationType::Direct,
                subject: 'Cross tenant',
                participantUserIds: [(string) $outsider->getKey()],
            );
            self::fail('Cross-organization participant was accepted.');
        } catch (BusinessRuleViolation) {
            self::assertTrue(true);
        }

        $post = app(PublishWallPostAction::class)->execute(
            organizationId: $organizationId,
            groupId: $groupId,
            authorUserId: (string) $teacher->getKey(),
            body: 'Class update',
        );
        app(AddWallCommentAction::class)->execute(
            post: $post,
            commenterUserId: (string) $student->getKey(),
            body: 'Received',
        );

        $assignmentResponse = $this->actingAs($teacher)->postJson('/api/assignments', [
            'course_id' => (string) $course->getKey(),
            'group_id' => $groupId,
            'staff_profile_id' => $staffProfileId,
            'title' => ['ar' => 'واجب', 'en' => 'Assignment'],
            'instructions' => ['ar' => 'التعليمات'],
            'assigned_at' => now()->subMinute()->utc()->toIso8601String(),
            'due_at' => now()->addDays(2)->utc()->toIso8601String(),
            'max_score' => 100,
            'allows_late' => true,
            'late_penalty_percent' => 0,
        ])->assertOk();
        $assignmentId = (string) $assignmentResponse->json('data.id');

        $this->actingAs($student)
            ->getJson('/api/assignments/'.$assignmentId)
            ->assertOk();
        $this->actingAs($outsider)
            ->getJson('/api/assignments/'.$assignmentId)
            ->assertForbidden();

        $submissionResponse = $this->actingAs($student)
            ->postJson('/api/assignments/'.$assignmentId.'/submit', ['content' => 'Student answer'])
            ->assertOk();
        $submissionId = (string) $submissionResponse->json('data.id');
        $this->actingAs($teacher)
            ->postJson('/api/assignment-submissions/'.$submissionId.'/grade', [
                'score' => 95,
                'feedback' => 'Good',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', AssignmentSubmissionStatus::Graded->value);

        self::assertSame(1, Assignment::query()->whereKey($assignmentId)->count());
        self::assertSame(1, AssignmentSubmission::query()->whereKey($submissionId)->count());

        DB::table('enrollments')->where('id', $enrollmentId)->update([
            'status' => 'frozen',
            'frozen_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);

        $this->expectException(BusinessRuleViolation::class);
        app(AddWallCommentAction::class)->execute(
            post: $post,
            commenterUserId: (string) $student->getKey(),
            body: 'Must be rejected after freeze',
        );
    }

    private function user(string $organizationId, string $label): User
    {
        return User::factory()->create([
            'organization_id' => $organizationId,
            'name' => $label,
            'email' => $label.'.'.strtolower((string) Str::ulid()).'@example.test',
        ]);
    }

    private function grant(User $user, string $permissionName): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => GuardName::Web->value, 'module' => 'Task04'],
        );
        ModelHasPermission::query()->firstOrCreate([
            'permission_id' => (string) $permission->getKey(),
            'model_type' => $user->getMorphClass(),
            'model_id' => (string) $user->getAuthIdentifier(),
        ]);
        app(PermissionGateRegistrar::class)->register();
    }

    private function studentProfile(string $organizationId, string $userId): string
    {
        $id = (string) Str::ulid();
        DB::table('student_profiles')->insert([
            'id' => $id,
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'student_code' => 'S'.substr($id, -8),
            'joined_at' => now()->subDay()->toDateString(),
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);

        return $id;
    }

    private function staffProfile(string $organizationId, string $userId): string
    {
        $id = (string) Str::ulid();
        DB::table('staff_profiles')->insert([
            'id' => $id,
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'staff_code' => 'T'.substr($id, -8),
            'employment_type' => 'per_session',
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);

        return $id;
    }

    private function guardianProfile(string $organizationId, string $userId): string
    {
        $id = (string) Str::ulid();
        DB::table('guardian_profiles')->insert([
            'id' => $id,
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);

        return $id;
    }
}
