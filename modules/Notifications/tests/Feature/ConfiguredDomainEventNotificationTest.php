<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;
use Modules\AccessControl\Domain\Models\ModelHasRole;
use Modules\AccessControl\Domain\Models\Role;
use Modules\Identity\Domain\Models\User;
use Modules\Notifications\Application\Jobs\SendQueuedNotification;
use Modules\Notifications\Application\Listeners\QueueConfiguredDomainEventNotification;
use Modules\Notifications\Application\Services\PayloadDomainEventRecipientResolver;
use Modules\Notifications\Database\Seeders\NotificationTemplateSeeder;
use Modules\Notifications\Domain\Contracts\DomainEventRecipientResolver;
use Modules\Notifications\Domain\Contracts\NotificationDispatcher;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Infrastructure\Gateways\MailChannelGateway;
use Modules\Notifications\Infrastructure\Mail\NotificationMail;
use Modules\Organization\Domain\Models\Organization;
use Shared\Domain\DomainEvent;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class ConfiguredSessionScheduledEvent extends DomainEvent
{
    public function __construct(
        public readonly string $organizationId,
        public readonly string $studentUserId,
        public readonly string $scheduledStart,
    ) {
        parent::__construct();
    }

    public function name(): string
    {
        return 'test.session.scheduled';
    }

    public function module(): string
    {
        return 'NotificationsTest';
    }

    public function payload(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'student_user_id' => $this->studentUserId,
            'scheduled_start' => $this->scheduledStart,
        ];
    }
}

final class ConditionalNotificationEvent extends DomainEvent
{
    public function name(): string
    {
        return 'test.conditional';
    }

    public function module(): string
    {
        return 'NotificationsTest';
    }

    public function payload(): array
    {
        return [
            'organization_id' => '01TESTORGANIZATION0000000',
            'teacher_user_id' => '01TESTTEACHERUSER00000000',
            'decision' => 'approved',
            'substitute_required' => true,
        ];
    }
}

it('runs a configured domain event through listener outbox job gateway and mail transport', function (): void {
    /** @var TestCase $this */
    Mail::fake();
    $this->seed(NotificationTemplateSeeder::class);
    $recipientId = Fixtures::userId();

    DB::table('users')->where('id', $recipientId)->update([
        'email' => 'event-recipient@example.test',
        'locale' => 'en',
        'timezone' => 'UTC',
    ]);

    config([
        'notifications.channels' => [
            'in_app' => ['enabled' => false],
            'email' => ['enabled' => true, 'gateway' => MailChannelGateway::class],
            'whatsapp' => ['enabled' => false],
        ],
        'notifications.categories.session_changed' => [
            'channels' => ['email'],
            'critical' => true,
        ],
        'notifications.events' => [
            'session.scheduled' => [
                'category' => 'session_changed',
                'recipient_fields' => ['student_user_id'],
                'source_events' => [ConfiguredSessionScheduledEvent::class],
            ],
        ],
    ]);
    Event::listen(
        ConfiguredSessionScheduledEvent::class,
        QueueConfiguredDomainEventNotification::class,
    );

    Event::dispatch(new ConfiguredSessionScheduledEvent(
        organizationId: Fixtures::organizationId(),
        studentUserId: $recipientId,
        scheduledStart: '2026-08-23T10:00:00Z',
    ));

    $outbox = NotificationOutbox::query()->sole();
    app()->call([new SendQueuedNotification($outbox->id), 'handle']);

    expect($outbox->refresh()->status)->toBe(OutboxStatus::Sent)
        ->and($outbox->event_name)->toBe('session.scheduled');

    Mail::assertSent(
        NotificationMail::class,
        fn (NotificationMail $mail): bool => $mail->hasTo('event-recipient@example.test')
            && $mail->renderedLocale() === 'en',
    );
});

it('dispatches every matching rule for the same domain event', function (): void {
    config()->set('notifications.events', [
        'teacher.apology.approved' => [
            'category' => 'teacher_workflow',
            'audiences' => ['teacher'],
            'recipient_fields' => ['teacher_user_id'],
            'source_events' => [ConditionalNotificationEvent::class],
            'payload_match' => ['decision' => 'approved'],
        ],
        'session.substitute.required' => [
            'category' => 'teacher_workflow',
            'audiences' => ['supervisor'],
            'recipient_fields' => [],
            'source_events' => [ConditionalNotificationEvent::class],
            'payload_match' => ['substitute_required' => true],
        ],
        'teacher.apology.rejected' => [
            'category' => 'teacher_workflow',
            'audiences' => ['teacher'],
            'recipient_fields' => ['teacher_user_id'],
            'source_events' => [ConditionalNotificationEvent::class],
            'payload_match' => ['decision' => 'rejected'],
        ],
    ]);

    $eventKeys = [];
    $resolver = Mockery::mock(DomainEventRecipientResolver::class);
    /** @var DomainEventRecipientResolver&MockInterface $resolver */
    $resolver->shouldReceive('resolve')
        ->twice()
        ->andReturnUsing(function (string $eventKey) use (&$eventKeys): array {
            $eventKeys[] = $eventKey;

            return ['01TESTRECIPIENT00000000000'];
        });

    $dispatcher = Mockery::mock(NotificationDispatcher::class);
    /** @var NotificationDispatcher&MockInterface $dispatcher */
    $dispatcher->shouldReceive('dispatch')->twice()->andReturn(1);

    (new QueueConfiguredDomainEventNotification($dispatcher, $resolver))
        ->handle(new ConditionalNotificationEvent);

    sort($eventKeys);
    expect($eventKeys)->toBe([
        'session.substitute.required',
        'teacher.apology.approved',
    ]);
});

it('adds operational role recipients only from the event organization', function (): void {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $directRecipient = User::factory()
        ->inOrganization((string) $organizationA->id)
        ->create();
    $supervisorA = User::factory()
        ->inOrganization((string) $organizationA->id)
        ->create();
    $supervisorB = User::factory()
        ->inOrganization((string) $organizationB->id)
        ->create();

    $role = Role::query()->create([
        'organization_id' => null,
        'name' => 'academic_supervisor',
        'guard_name' => 'web',
        'is_system' => true,
    ]);
    foreach ([$supervisorA, $supervisorB] as $supervisor) {
        ModelHasRole::query()->create([
            'role_id' => (string) $role->id,
            'model_type' => User::class,
            'model_id' => (string) $supervisor->id,
        ]);
    }

    config()->set('notifications.audience_roles.supervisor', ['academic_supervisor']);
    $recipients = app(PayloadDomainEventRecipientResolver::class)->resolve(
        eventKey: 'postponement.requested',
        audiences: ['supervisor'],
        recipientFields: ['teacher_user_id'],
        payload: [
            'organization_id' => (string) $organizationA->id,
            'teacher_user_id' => (string) $directRecipient->id,
        ],
    );

    sort($recipients);
    $expected = [(string) $directRecipient->id, (string) $supervisorA->id];
    sort($expected);

    expect($recipients)->toBe($expected)
        ->and($recipients)->not->toContain((string) $supervisorB->id);
});
