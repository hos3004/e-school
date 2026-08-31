<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\Notifications\Application\Jobs\SendQueuedNotification;
use Modules\Notifications\Database\Seeders\NotificationTemplateSeeder;
use Modules\Notifications\Domain\Contracts\NotificationDispatcher;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationDeliveryAttempt;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Infrastructure\Gateways\InAppChannelGateway;
use Modules\Notifications\Infrastructure\Gateways\MailChannelGateway;
use Modules\Notifications\Infrastructure\Mail\NotificationMail;
use Shared\Testing\Fixtures;
use Tests\TestCase;

function mailPipelineRecipient(string $email, string $locale = 'en'): string
{
    $userId = Fixtures::userId();

    DB::table('users')->where('id', $userId)->update([
        'email' => $email,
        'locale' => $locale,
        'timezone' => 'UTC',
    ]);

    return $userId;
}

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->seed(NotificationTemplateSeeder::class);

    config([
        'notifications.channels' => [
            'in_app' => ['enabled' => false, 'gateway' => InAppChannelGateway::class],
            'email' => ['enabled' => true, 'gateway' => MailChannelGateway::class],
            'whatsapp' => ['enabled' => false],
        ],
        'notifications.categories.session_changed' => [
            'channels' => ['in_app', 'email', 'whatsapp'],
            'critical' => true,
        ],
        'notifications.quiet_hours.enabled' => false,
    ]);
});

it('delivers a localized outbox notification through the Laravel Mail transport', function (): void {
    /** @var TestCase $this */
    Mail::fake();
    $recipientId = mailPipelineRecipient('student@example.test', 'en');
    $scheduledStart = '2026-08-23T10:00:00Z';

    $written = app(NotificationDispatcher::class)->dispatch(
        category: 'session_changed',
        recipientIds: [$recipientId],
        payload: [
            'event_name' => 'session.scheduled',
            'event_id' => (string) Str::ulid(),
            'scheduled_start' => $scheduledStart,
        ],
    );

    $outbox = NotificationOutbox::query()->sole();
    app()->call([new SendQueuedNotification($outbox->id), 'handle']);

    expect($written)->toBe(1)
        ->and($outbox->refresh()->status)->toBe(OutboxStatus::Sent)
        ->and(NotificationDeliveryAttempt::query()->sole()->succeeded)->toBeTrue();

    Mail::assertSent(
        NotificationMail::class,
        fn (NotificationMail $mail): bool => $mail->hasTo('student@example.test')
            && $mail->renderedLocale() === 'en'
            && $mail->renderedSubject() === 'Session scheduled'
            && str_contains($mail->renderedBody(), '2026-08-23 10:00 UTC'),
    );
});

it('keeps in-app delivery successful when the email channel has a permanent recipient failure', function (): void {
    /** @var TestCase $this */
    Mail::fake();
    config(['notifications.channels.in_app.enabled' => true]);
    $recipientId = mailPipelineRecipient('not-an-email');

    app(NotificationDispatcher::class)->dispatch(
        category: 'session_changed',
        recipientIds: [$recipientId],
        payload: [
            'event_name' => 'session.scheduled',
            'event_id' => (string) Str::ulid(),
            'scheduled_start' => '2026-08-23T10:00:00Z',
        ],
    );

    NotificationOutbox::query()->each(
        fn (NotificationOutbox $outbox) => app()->call([new SendQueuedNotification($outbox->id), 'handle']),
    );

    expect(NotificationOutbox::query()
        ->where('channel', Channel::InApp)
        ->sole()
        ->status)->toBe(OutboxStatus::Sent)
        ->and(NotificationOutbox::query()
            ->where('channel', Channel::Email)
            ->sole()
            ->status)->toBe(OutboxStatus::Failed)
        ->and(NotificationOutbox::query()
            ->where('channel', Channel::Email)
            ->sole()
            ->last_error_retryable)->toBeFalse();

    Mail::assertNothingSent();
});
