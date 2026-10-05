<?php

declare(strict_types=1);

namespace Modules\Messaging\Tests\Feature\Authorization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Identity\Domain\Models\User;
use Modules\Messaging\Application\Actions\CreateConversationAction;
use Modules\Messaging\Domain\Enums\ConversationType;
use Modules\Messaging\Domain\Enums\ParticipantRole;
use Modules\Messaging\Domain\Models\ConversationParticipant;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class GuardianPrivacyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Fixtures::flush();
        $this->seed(AccessControlSeeder::class);
    }

    private function grantDirectPermission(User $user, string $permissionName): void
    {
        $perm = Permission::query()->firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => GuardName::Web->value, 'module' => 'Messaging'],
        );

        ModelHasPermission::query()->create([
            'permission_id' => (string) $perm->getKey(),
            'model_type' => $user->getMorphClass(),
            'model_id' => (string) $user->getAuthIdentifier(),
        ]);

        app(PermissionGateRegistrar::class)->register();
    }

    public function test_guardian_cannot_access_student_teacher_conversation_via_http(): void
    {
        $orgId = Fixtures::organizationId();

        $studentUserId = Fixtures::userId();
        $teacherUserId = Fixtures::userId();
        $guardianUserId = Fixtures::userId();

        /** @var User $guardianUser */
        $guardianUser = User::query()->findOrFail($guardianUserId);
        $this->grantDirectPermission($guardianUser, 'guardian.view');
        $this->grantDirectPermission($guardianUser, 'message.send');
        $this->createLinkedProfiles($orgId, $studentUserId, $teacherUserId, $guardianUserId);

        // Create direct conversation between student and teacher
        $conversation = app(CreateConversationAction::class)->execute(
            organizationId: $orgId,
            creatorUserId: $studentUserId,
            type: ConversationType::Direct,
            subject: 'Student Teacher Direct Chat',
            participantUserIds: [$teacherUserId],
        );

        ConversationParticipant::query()->create([
            'organization_id' => $orgId,
            'conversation_id' => (string) $conversation->id,
            'user_id' => $guardianUserId,
            'role' => ParticipantRole::Member->value,
            'joined_at' => now('UTC'),
        ]);

        $this->actingAs($guardianUser)
            ->getJson("/api/messaging/conversations/{$conversation->id}")
            ->assertForbidden();

        $this->actingAs($guardianUser)
            ->getJson("/api/conversations/{$conversation->id}/messages")
            ->assertForbidden();

        $this->actingAs($guardianUser)
            ->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Forbidden'])
            ->assertForbidden();

        $this->actingAs($guardianUser)
            ->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_teacher_participant_can_access_conversation_even_with_guardian_view_permission(): void
    {
        $orgId = Fixtures::organizationId();

        $studentUserId = Fixtures::userId();
        $teacherUserId = Fixtures::userId();

        /** @var User $teacherUser */
        $teacherUser = User::query()->findOrFail($teacherUserId);
        $this->grantDirectPermission($teacherUser, 'message.send');
        $this->grantDirectPermission($teacherUser, 'guardian.view');

        $conversation = app(CreateConversationAction::class)->execute(
            organizationId: $orgId,
            creatorUserId: $studentUserId,
            type: ConversationType::Direct,
            subject: 'Student Teacher Direct Chat',
            participantUserIds: [$teacherUserId],
        );

        $this->actingAs($teacherUser)
            ->getJson("/api/messaging/conversations/{$conversation->id}")
            ->assertOk();
    }

    public function test_supervisor_with_moderate_permission_can_access_conversation(): void
    {
        $orgId = Fixtures::organizationId();

        $studentUserId = Fixtures::userId();
        $teacherUserId = Fixtures::userId();
        $supervisorUserId = Fixtures::userId();

        /** @var User $supervisorUser */
        $supervisorUser = User::query()->findOrFail($supervisorUserId);
        $this->grantDirectPermission($supervisorUser, 'classroom.moderate');

        $conversation = app(CreateConversationAction::class)->execute(
            organizationId: $orgId,
            creatorUserId: $studentUserId,
            type: ConversationType::Direct,
            subject: 'Student Teacher Direct Chat',
            participantUserIds: [$teacherUserId],
        );

        $this->actingAs($supervisorUser)
            ->getJson("/api/messaging/conversations/{$conversation->id}")
            ->assertOk();
    }

    public function test_list_paginates_after_excluding_denied_guardian_rows(): void
    {
        $orgId = Fixtures::organizationId();
        $studentUserId = Fixtures::userId();
        $teacherUserId = Fixtures::userId();
        $guardianUserId = Fixtures::userId();

        /** @var User $guardianUser */
        $guardianUser = User::query()->findOrFail($guardianUserId);
        $this->grantDirectPermission($guardianUser, 'guardian.view');
        $this->grantDirectPermission($guardianUser, 'message.send');
        $this->createLinkedProfiles($orgId, $studentUserId, $teacherUserId, $guardianUserId);

        for ($index = 0; $index < 17; $index++) {
            $peerUserId = Fixtures::userId();
            app(CreateConversationAction::class)->execute(
                organizationId: $orgId,
                creatorUserId: $guardianUserId,
                type: ConversationType::Direct,
                subject: 'Allowed '.$index,
                participantUserIds: [$peerUserId],
            );
        }

        $deniedIds = [];
        for ($index = 0; $index < 3; $index++) {
            $denied = app(CreateConversationAction::class)->execute(
                organizationId: $orgId,
                creatorUserId: $studentUserId,
                type: ConversationType::Direct,
                subject: 'Denied '.$index,
                participantUserIds: [$teacherUserId],
            );
            $deniedIds[] = (string) $denied->id;

            ConversationParticipant::query()->create([
                'organization_id' => $orgId,
                'conversation_id' => (string) $denied->id,
                'user_id' => $guardianUserId,
                'role' => ParticipantRole::Member->value,
                'joined_at' => now('UTC'),
            ]);
        }

        $first = $this->actingAs($guardianUser)->getJson('/api/conversations?page=1');
        $first->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('meta.total', 17);

        $second = $this->actingAs($guardianUser)->getJson('/api/conversations?page=2');
        $second->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 17);

        $visibleIds = array_column(array_merge(
            $first->json('data'),
            $second->json('data'),
        ), 'id');

        self::assertCount(17, array_unique($visibleIds));
        self::assertSame([], array_values(array_intersect($deniedIds, $visibleIds)));
    }

    private function createLinkedProfiles(
        string $organizationId,
        string $studentUserId,
        string $teacherUserId,
        string $guardianUserId,
    ): void {
        $studentProfileId = Fixtures::studentProfileForUser($studentUserId);
        Fixtures::staffProfileForUser($teacherUserId);
        $guardianProfileId = (string) Str::ulid();

        DB::table('guardian_profiles')->insert([
            'id' => $guardianProfileId,
            'organization_id' => $organizationId,
            'user_id' => $guardianUserId,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        DB::table('guardian_links')->insert([
            'id' => (string) Str::ulid(),
            'guardian_profile_id' => $guardianProfileId,
            'student_profile_id' => $studentProfileId,
            'relationship' => 'father',
            'is_primary' => true,
            'can_act_for' => true,
            'verified_at' => now('UTC'),
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }
}
