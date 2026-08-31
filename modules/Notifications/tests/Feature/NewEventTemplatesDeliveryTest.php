<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\Integrations\Infrastructure\Gateways\WhatsAppCloudGateway;
use Modules\Notifications\Application\Jobs\SendQueuedNotification;
use Modules\Notifications\Database\Seeders\NotificationTemplateSeeder;
use Modules\Notifications\Domain\Contracts\NotificationDispatcher;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Infrastructure\Gateways\MailChannelGateway;
use Modules\Notifications\Infrastructure\Mail\NotificationMail;
use Shared\Testing\Fixtures;

/**
 * يثبت أن الأحداث التي كانت بلا قوالب (الانضباط · التجميد · الواجبات · الدرجات)
 * صارت تُسلَّم فعليًا عبر واتساب والبريد بعد إضافة قوالبها.
 */
beforeEach(function (): void {
    /** @var \Tests\TestCase $this */
    $this->seed(NotificationTemplateSeeder::class);
    config(['notifications.quiet_hours.enabled' => false]);
});

it('delivers the enrollment-frozen event through the WhatsApp template gateway', function (): void {
    /** @var \Tests\TestCase $this */
    config([
        'notifications.channels' => [
            'in_app' => ['enabled' => false],
            'email' => ['enabled' => false],
            'whatsapp' => [
                'enabled' => true,
                'gateway' => WhatsAppCloudGateway::class,
                'token' => 'test-token',
                'phone_number_id' => '123456789',
                'api_version' => 'v23.0',
                'timeout_seconds' => 5,
                'retry_delays_milliseconds' => [],
            ],
        ],
        'notifications.categories.enrollment_frozen' => [
            'channels' => ['whatsapp'],
            'critical' => true,
        ],
    ]);

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messages' => [['id' => 'wamid.frozen', 'message_status' => 'accepted']],
        ]),
    ]);

    $userId = Fixtures::userId();
    DB::table('users')->where('id', $userId)->update([
        'phone' => '01001234567',
        'phone_country' => 'EG',
        'locale' => 'ar',
        'timezone' => 'UTC',
    ]);

    app(NotificationDispatcher::class)->dispatch(
        category: 'enrollment_frozen',
        recipientIds: [$userId],
        payload: [
            'event_name' => 'discipline.student_frozen',
            'event_id' => (string) Str::ulid(),
        ],
    );

    $outbox = NotificationOutbox::query()->sole();
    app()->call([new SendQueuedNotification($outbox->id), 'handle']);

    expect($outbox->refresh()->status)->toBe(OutboxStatus::Sent)
        ->and($outbox->external_message_id)->toBe('wamid.frozen');

    Http::assertSent(fn (Request $request): bool => $request['type'] === 'template'
        && $request['template']['name'] === 'discipline_student_frozen'
        && $request['template']['language']['code'] === 'ar');
});

it('delivers the graded-submission event through email with its score parameters', function (): void {
    /** @var \Tests\TestCase $this */
    Mail::fake();

    config([
        'notifications.channels' => [
            'in_app' => ['enabled' => false],
            'email' => ['enabled' => true, 'gateway' => MailChannelGateway::class],
            'whatsapp' => ['enabled' => false],
        ],
        'notifications.categories.grade_published' => [
            'channels' => ['email'],
            'critical' => false,
            'respects_quiet_hours' => false,
        ],
    ]);

    $userId = Fixtures::userId();
    DB::table('users')->where('id', $userId)->update([
        'email' => 'graded@example.test',
        'locale' => 'en',
        'timezone' => 'UTC',
    ]);

    app(NotificationDispatcher::class)->dispatch(
        category: 'grade_published',
        recipientIds: [$userId],
        payload: [
            'event_name' => 'submission.graded',
            'event_id' => (string) Str::ulid(),
            'score' => 8,
            'max_score' => 10,
        ],
    );

    $outbox = NotificationOutbox::query()->sole();
    app()->call([new SendQueuedNotification($outbox->id), 'handle']);

    expect($outbox->refresh()->status)->toBe(OutboxStatus::Sent);

    Mail::assertSent(
        NotificationMail::class,
        fn (NotificationMail $mail): bool => $mail->hasTo('graded@example.test')
            && $mail->renderedSubject() === 'Assignment graded'
            && str_contains($mail->renderedBody(), '8 out of 10'),
    );
});
