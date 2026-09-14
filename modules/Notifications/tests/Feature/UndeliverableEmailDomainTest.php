<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\Notifications\Application\Jobs\SendQueuedNotification;
use Modules\Notifications\Database\Seeders\NotificationTemplateSeeder;
use Modules\Notifications\Domain\Contracts\NotificationDispatcher;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationDeliveryAttempt;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Infrastructure\Gateways\MailChannelGateway;
use Shared\Testing\Fixtures;
use Tests\TestCase;

/*
 * العناوين على نطاقات محجوزة لا تُفتح لها محاولة إرسال أصلًا.
 *
 * الحسابات المستوردة بلا بريد حقيقي تحمل عناوين ‎@…​.invalid. خادم البريد
 * يردّ عليها 450 وهو رمز مؤقت، فكان المحرّك يعيد المحاولة إلى الأبد: عشرات
 * آلاف المحاولات تحجز عمال الطابور وتُجوّع بقية القنوات. الرفض هنا نهائي
 * ويقع قبل أي اتصال، ولذلك لا يلتقطه أمر إعادة المحاولة بعدها.
 */
beforeEach(function (): void {
    /** @var TestCase $this */
    $this->seed(NotificationTemplateSeeder::class);

    config([
        'notifications.channels' => [
            'in_app' => ['enabled' => false],
            'email' => [
                'enabled' => true,
                'gateway' => MailChannelGateway::class,
                'undeliverable_domains' => ['invalid', 'test', 'example', 'localhost', 'local'],
            ],
        ],
        'notifications.categories.session_changed' => [
            'channels' => ['email'],
            'critical' => true,
        ],
        'notifications.quiet_hours.enabled' => false,
    ]);
});

function outboxForEmail(string $email): NotificationOutbox
{
    $userId = Fixtures::userId();

    DB::table('users')->where('id', $userId)->update([
        'email' => $email,
        'locale' => 'ar',
        'timezone' => 'UTC',
    ]);

    app(NotificationDispatcher::class)->dispatch(
        category: 'session_changed',
        recipientIds: [$userId],
        payload: [
            'event_name' => 'session.scheduled',
            'event_id' => (string) Str::ulid(),
            'scheduled_start' => '2026-08-23T10:00:00Z',
        ],
    );

    return NotificationOutbox::query()->where('user_id', $userId)->sole();
}

it('never opens a mail connection for a reserved recipient domain', function (): void {
    /** @var TestCase $this */
    Mail::fake();
    Bus::fake([SendQueuedNotification::class]);

    $outbox = outboxForEmail('s1041@accounts.telecourse.invalid');
    app()->call([new SendQueuedNotification($outbox->id), 'handle']);
    $attempt = NotificationDeliveryAttempt::query()->sole();

    expect($outbox->refresh()->status)->toBe(OutboxStatus::Failed)
        // نهائي لا مؤقت: هذا ما يمنع أمر إعادة المحاولة من التقاطها ثانيةً.
        ->and($outbox->last_error_retryable)->toBeFalse()
        ->and($attempt->retryable)->toBeFalse()
        ->and($attempt->provider_response)->toMatchArray([
            'failure_reason' => 'email_domain_undeliverable',
        ]);

    Mail::assertNothingSent();
    Bus::assertNotDispatched(SendQueuedNotification::class);
});

it('treats a bare reserved domain the same as a subdomain of it', function (): void {
    /** @var TestCase $this */
    Mail::fake();

    $outbox = outboxForEmail('someone@invalid');
    app()->call([new SendQueuedNotification($outbox->id), 'handle']);

    expect($outbox->refresh()->status)->toBe(OutboxStatus::Failed);
    Mail::assertNothingSent();
});

it('still delivers to a real recipient domain', function (): void {
    /** @var TestCase $this */
    Mail::fake();

    $outbox = outboxForEmail('parent@gmail.com');
    app()->call([new SendQueuedNotification($outbox->id), 'handle']);

    // الحارس يخص النطاقات المحجوزة وحدها؛ لا يمس البريد الحقيقي.
    expect($outbox->refresh()->status)->toBe(OutboxStatus::Sent);
    Mail::assertSentCount(1);
});

it('cancels the queued backlog addressed to reserved domains', function (): void {
    /** @var TestCase $this */
    Mail::fake();

    $stuck = outboxForEmail('s1053@accounts.telecourse.invalid');
    $real = outboxForEmail('guardian@gmail.com');

    $this->artisan('notifications:cancel-undeliverable')->assertSuccessful();

    expect($stuck->refresh()->status)->toBe(OutboxStatus::Cancelled)
        // الرسالة إلى عنوان حقيقي لا تُمس.
        ->and($real->refresh()->status)->toBe(OutboxStatus::Queued);
});

it('changes nothing on a dry run', function (): void {
    /** @var TestCase $this */
    Mail::fake();

    $stuck = outboxForEmail('s1017@accounts.telecourse.invalid');

    $this->artisan('notifications:cancel-undeliverable', ['--dry-run' => true])->assertSuccessful();

    expect($stuck->refresh()->status)->toBe(OutboxStatus::Queued);
});
