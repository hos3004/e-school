<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Modules\Notifications\Application\Jobs\SendQueuedNotification;
use Modules\Notifications\Application\Listeners\QueueConfiguredDomainEventNotification;
use Modules\Notifications\Database\Seeders\NotificationTemplateSeeder;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Infrastructure\Gateways\MailChannelGateway;
use Modules\Notifications\Infrastructure\Mail\NotificationMail;
use Shared\Domain\DomainEvent;
use Shared\Testing\Fixtures;

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

it('runs a configured domain event through listener outbox job gateway and mail transport', function (): void {
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
