<?php

declare(strict_types=1);

namespace Modules\Notifications\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Audit\Domain\Models\AuditLog;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupMembership;
use Modules\Identity\Domain\Models\User;
use Modules\Notifications\Application\Actions\QueueManualNotificationAction;
use Modules\Notifications\Application\Services\ManualNotificationRecipientResolver;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\ManualRecipientType;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Domain\ValueObjects\ManualNotificationDispatchResult;
use Modules\Notifications\Presentation\Filament\Resources\NotificationOutboxResource;
use Modules\Organization\Domain\Models\Organization;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\BusinessRuleViolation;
use Tests\TestCase;

final class ManualNotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notifications.channels.in_app.enabled' => true,
            'notifications.channels.email.enabled' => false,
        ]);
    }

    public function test_student_teacher_and_group_are_tenant_scoped_queued_and_audited(): void
    {
        $organization = Organization::factory()->create();
        $foreignOrganization = Organization::factory()->create();
        $actor = User::factory()->inOrganization((string) $organization->id)->create();
        [$student, $studentProfile] = $this->student($organization, 'الطالب الأول');
        [, $secondProfile] = $this->student($organization, 'الطالب الثاني');
        [$teacher, $teacherProfile] = $this->teacher($organization, 'المعلم الأول');
        [$foreignStudent] = $this->student($foreignOrganization, 'طالب أجنبي');
        $group = Group::factory()->active()->create([
            'organization_id' => (string) $organization->id,
            'name' => ['ar' => 'مجموعة الإشعار', 'en' => 'Notification group'],
        ]);
        GroupMembership::factory()->create([
            'group_id' => (string) $group->id,
            'student_profile_id' => (string) $studentProfile->id,
        ]);
        GroupMembership::factory()->create([
            'group_id' => (string) $group->id,
            'student_profile_id' => (string) $secondProfile->id,
        ]);

        $resolver = app(ManualNotificationRecipientResolver::class);
        self::assertArrayHasKey(
            (string) $student->id,
            $resolver->search((string) $organization->id, ManualRecipientType::Student, 'الأول'),
        );
        self::assertArrayHasKey(
            (string) $teacher->id,
            $resolver->search((string) $organization->id, ManualRecipientType::Teacher, 'المعلم'),
        );
        self::assertArrayHasKey(
            (string) $group->id,
            $resolver->search((string) $organization->id, ManualRecipientType::Group, 'الإشعار'),
        );
        self::assertArrayNotHasKey(
            (string) $foreignStudent->id,
            $resolver->search((string) $organization->id, ManualRecipientType::Student, 'أجنبي'),
        );

        $action = app(QueueManualNotificationAction::class);
        $studentResult = $this->dispatch($action, $organization, $actor, ManualRecipientType::Student, (string) $student->id);
        $teacherResult = $this->dispatch($action, $organization, $actor, ManualRecipientType::Teacher, (string) $teacher->id);
        $groupResult = $this->dispatch($action, $organization, $actor, ManualRecipientType::Group, (string) $group->id);

        self::assertSame(1, $studentResult->queuedCount);
        self::assertSame(1, $teacherResult->queuedCount);
        self::assertSame(2, $groupResult->queuedCount);
        self::assertSame(2, $groupResult->recipientCount);
        self::assertSame(4, NotificationOutbox::query()->count());
        self::assertSame(
            [OutboxStatus::Queued],
            NotificationOutbox::query()->distinct()->pluck('status')->all(),
        );
        self::assertSame(
            [Channel::InApp->value],
            NotificationOutbox::query()->distinct()->pluck('channel')->all(),
        );
        self::assertSame(
            [(string) $organization->id],
            NotificationOutbox::query()->distinct()->pluck('organization_id')->all(),
        );
        self::assertSame(3, AuditLog::query()->where('action', 'notifications.manual_dispatched')->count());

        $audit = AuditLog::query()->where('action', 'notifications.manual_dispatched')->firstOrFail();
        self::assertArrayNotHasKey('subject', $audit->new_values ?? []);
        self::assertArrayNotHasKey('body', $audit->new_values ?? []);
        self::assertSame((string) $actor->id, (string) $audit->getAttribute('actor_id'));
        self::assertSame((string) $teacherProfile->user_id, (string) $teacher->id);
    }

    public function test_double_submit_is_idempotent_and_does_not_repeat_audit(): void
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->inOrganization((string) $organization->id)->create();
        [$student] = $this->student($organization, 'طالب عدم التكرار');
        $requestId = (string) str()->ulid();
        $action = app(QueueManualNotificationAction::class);

        $first = $this->dispatch(
            $action,
            $organization,
            $actor,
            ManualRecipientType::Student,
            (string) $student->id,
            $requestId,
        );
        $second = $this->dispatch(
            $action,
            $organization,
            $actor,
            ManualRecipientType::Student,
            (string) $student->id,
            $requestId,
        );

        self::assertFalse($first->alreadyProcessed);
        self::assertTrue($second->alreadyProcessed);
        self::assertSame(1, NotificationOutbox::query()->count());
        self::assertSame(1, AuditLog::query()->where('auditable_id', $requestId)->count());
    }

    public function test_empty_group_foreign_recipient_and_unconfigured_channel_fail_closed(): void
    {
        $organization = Organization::factory()->create();
        $foreignOrganization = Organization::factory()->create();
        $actor = User::factory()->inOrganization((string) $organization->id)->create();
        [$foreignStudent] = $this->student($foreignOrganization, 'طالب أجنبي');
        $emptyGroup = Group::factory()->active()->create(['organization_id' => (string) $organization->id]);
        $action = app(QueueManualNotificationAction::class);

        try {
            $this->dispatch(
                $action,
                $organization,
                $actor,
                ManualRecipientType::Group,
                (string) $emptyGroup->id,
            );
            self::fail('Expected the empty group to be rejected.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('notifications.manual_empty_audience', $violation->rule);
        }

        try {
            $this->dispatch(
                $action,
                $organization,
                $actor,
                ManualRecipientType::Student,
                (string) $foreignStudent->id,
            );
            self::fail('Expected the foreign recipient to be rejected.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('notifications.manual_recipient_not_found', $violation->rule);
        }

        try {
            $action->execute(
                organizationId: (string) $organization->id,
                actorId: (string) $actor->id,
                recipientType: ManualRecipientType::Student,
                targetId: (string) $foreignStudent->id,
                channel: Channel::Email,
                subject: 'Subject',
                body: 'Body',
                reason: 'Administrative reason',
                requestId: (string) str()->ulid(),
                locale: 'en',
            );
            self::fail('Expected the unavailable channel to be rejected.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('notifications.channel_disabled', $violation->rule);
        }

        self::assertSame(0, NotificationOutbox::query()->count());
        self::assertSame(0, AuditLog::query()->where('action', 'notifications.manual_dispatched')->count());
    }

    public function test_api_derives_organization_checks_recipient_and_rejects_client_organization(): void
    {
        $organization = Organization::factory()->create();
        $foreignOrganization = Organization::factory()->create();
        $actor = User::factory()->inOrganization((string) $organization->id)->create();
        $denied = User::factory()->inOrganization((string) $organization->id)->create();
        $localRecipient = User::factory()->inOrganization((string) $organization->id)->create();
        $foreignRecipient = User::factory()->inOrganization((string) $foreignOrganization->id)->create();

        Gate::define(
            'notifications.outbox.create',
            static fn (User $user): bool => (string) $user->id === (string) $actor->id,
        );

        $this->actingAs($actor)
            ->postJson('/api/notifications', $this->apiPayload((string) $localRecipient->id))
            ->assertSuccessful();

        $stored = NotificationOutbox::query()->firstOrFail();
        self::assertSame((string) $organization->id, (string) $stored->organization_id);
        self::assertSame((string) $localRecipient->id, (string) $stored->user_id);

        $this->actingAs($actor)
            ->postJson('/api/notifications', $this->apiPayload((string) $foreignRecipient->id))
            ->assertNotFound();

        $payload = $this->apiPayload((string) $localRecipient->id);
        $payload['organization_id'] = (string) $foreignOrganization->id;
        $this->actingAs($actor)
            ->postJson('/api/notifications', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('organization_id');

        $this->actingAs($denied)
            ->postJson('/api/notifications', $this->apiPayload((string) $localRecipient->id))
            ->assertForbidden();

        self::assertSame(1, NotificationOutbox::query()->count());
    }

    public function test_authorized_operator_sees_send_notification_cta(): void
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->inOrganization((string) $organization->id)->create();

        Gate::define(
            'notifications.outbox.create',
            static fn (User $user): bool => (string) $user->id === (string) $actor->id,
        );
        Gate::define('admin.panel.access', static fn (): bool => true);
        Filament::setCurrentPanel('admin');

        $this->actingAs($actor)
            ->get(NotificationOutboxResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText(__('notifications::actions.send_notification'));
    }

    /**
     * @return array{0: User, 1: StudentProfile}
     */
    private function student(Organization $organization, string $name): array
    {
        $user = User::factory()->inOrganization((string) $organization->id)->create(['name' => $name]);
        $profile = StudentProfile::factory()->create([
            'organization_id' => (string) $organization->id,
            'user_id' => (string) $user->id,
        ]);

        return [$user, $profile];
    }

    /**
     * @return array{0: User, 1: StaffProfile}
     */
    private function teacher(Organization $organization, string $name): array
    {
        $user = User::factory()->inOrganization((string) $organization->id)->create(['name' => $name]);
        $profile = StaffProfile::query()->create([
            'organization_id' => (string) $organization->id,
            'user_id' => (string) $user->id,
            'staff_code' => 'T-NOTIFY-'.str()->random(6),
            'employment_type' => EmploymentType::FullTime,
            'hired_at' => now('UTC')->subYear()->toDateString(),
        ]);

        return [$user, $profile];
    }

    private function dispatch(
        QueueManualNotificationAction $action,
        Organization $organization,
        User $actor,
        ManualRecipientType $type,
        string $targetId,
        ?string $requestId = null,
    ): ManualNotificationDispatchResult {
        return $action->execute(
            organizationId: (string) $organization->id,
            actorId: (string) $actor->id,
            recipientType: $type,
            targetId: $targetId,
            channel: Channel::InApp,
            subject: 'تنبيه إداري',
            body: 'هذه رسالة داخل التطبيق.',
            reason: 'إرسال مطلوب لاختبار رحلة العميل',
            requestId: $requestId ?? (string) str()->ulid(),
            locale: 'ar',
        );
    }

    /** @return array<string, mixed> */
    private function apiPayload(string $userId): array
    {
        return [
            'user_id' => $userId,
            'category' => 'system_alert',
            'channel' => Channel::InApp->value,
            'event_name' => 'notifications.manual-api',
            'event_id' => (string) str()->ulid(),
            'locale' => 'ar',
            'subject' => ['ar' => 'تنبيه'],
            'body' => ['ar' => 'رسالة محصورة بالمؤسسة'],
        ];
    }
}
