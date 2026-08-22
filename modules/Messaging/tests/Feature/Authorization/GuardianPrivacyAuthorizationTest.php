<?php

declare(strict_types=1);

namespace Modules\Messaging\Tests\Feature\Authorization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Identity\Domain\Models\User;
use Modules\Messaging\Application\Actions\CreateConversationAction;
use Modules\Messaging\Domain\Enums\ConversationType;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class GuardianPrivacyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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

        // Create direct conversation between student and teacher
        $conversation = app(CreateConversationAction::class)->execute(
            organizationId: $orgId,
            creatorUserId: $studentUserId,
            type: ConversationType::Direct,
            subject: 'Student Teacher Direct Chat',
            participantUserIds: [$teacherUserId],
        );

        $this->actingAs($guardianUser)
            ->getJson("/api/messaging/conversations/{$conversation->id}")
            ->assertForbidden();
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
}
