<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Notifications\Application\Actions\QueueManualNotificationAction;
use Modules\Notifications\Application\Actions\QueueNotificationAction;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\ManualRecipientType;
use Shared\Testing\Fixtures;

/**
 * كل رسالة واتساب يدوية فشلت بـwhatsapp_payload_invalid: المسار اليدوي كان
 * يكتب الحمولة كما وصلته من المؤلِّف — بلا هاتف ولا بريد — بخلاف المسار
 * التلقائي الذي يملأهما من سجل المستلم. 18 سبتمبر 2026.
 */
it('carries the recipient phone into a manual whatsapp send', function (): void {
    $organizationId = Fixtures::organizationId();
    $studentProfileId = Fixtures::studentProfileId();
    $userId = (string) DB::table('student_profiles')->where('id', $studentProfileId)->value('user_id');
    DB::table('users')->where('id', $userId)->update([
        'phone' => '+970599776130',
        'phone_country' => null,
    ]);

    $result = app(QueueManualNotificationAction::class)->execute(
        organizationId: $organizationId,
        actorId: Fixtures::userId(),
        recipientType: ManualRecipientType::Student,
        targetId: $userId,
        channel: Channel::Whatsapp,
        subject: 'تم قبولكم',
        body: 'مرحبًا بكم في البرنامج',
        reason: 'اختبار',
        requestId: (string) Str::ulid(),
        locale: 'ar',
    );

    expect($result->queuedCount)->toBe(1);

    $row = DB::table('notification_outbox')
        ->where('event_name', 'notifications.manual')
        ->where('user_id', $userId)
        ->latest('created_at')
        ->first();

    $payload = json_decode((string) $row->payload, true);

    expect($payload['phone'] ?? null)->toBe('+970599776130');
});

it('carries the recipient email into a manual email send', function (): void {
    $organizationId = Fixtures::organizationId();
    $studentProfileId = Fixtures::studentProfileId();
    $userId = (string) DB::table('student_profiles')->where('id', $studentProfileId)->value('user_id');
    $email = DB::table('users')->where('id', $userId)->value('email');

    app(QueueManualNotificationAction::class)->execute(
        organizationId: $organizationId,
        actorId: Fixtures::userId(),
        recipientType: ManualRecipientType::Student,
        targetId: $userId,
        channel: Channel::Email,
        subject: 'تم قبولكم',
        body: 'مرحبًا بكم في البرنامج',
        reason: 'اختبار',
        requestId: (string) Str::ulid(),
        locale: 'ar',
    );

    $row = DB::table('notification_outbox')
        ->where('event_name', 'notifications.manual')
        ->where('user_id', $userId)
        ->latest('created_at')
        ->first();

    $payload = json_decode((string) $row->payload, true);

    expect($payload['email'] ?? null)->toBe($email);
});

it('does not touch the payload for channels with no contact field', function (): void {
    $organizationId = Fixtures::organizationId();
    $userId = Fixtures::userId();

    app(QueueNotificationAction::class)->execute(
        organizationId: $organizationId,
        userId: $userId,
        category: 'system_alert',
        channel: Channel::InApp,
        eventName: 'test.in_app',
        eventId: (string) Str::ulid(),
        subject: ['ar' => 'عنوان'],
        body: ['ar' => 'نص'],
    );

    $row = DB::table('notification_outbox')
        ->where('event_name', 'test.in_app')
        ->where('user_id', $userId)
        ->latest('created_at')
        ->first();

    $payload = json_decode((string) $row->payload, true);

    expect($payload)->not->toHaveKey('phone')
        ->and($payload)->not->toHaveKey('email');
});

it('does not overwrite a phone the caller already supplied', function (): void {
    $organizationId = Fixtures::organizationId();
    $userId = Fixtures::userId();
    DB::table('users')->where('id', $userId)->update(['phone' => '+201000000000']);

    app(QueueNotificationAction::class)->execute(
        organizationId: $organizationId,
        userId: $userId,
        category: 'system_alert',
        channel: Channel::Whatsapp,
        eventName: 'test.explicit_phone',
        eventId: (string) Str::ulid(),
        subject: ['ar' => 'عنوان'],
        body: ['ar' => 'نص'],
        payload: ['phone' => '+15551234567', 'phone_country' => null],
    );

    $row = DB::table('notification_outbox')
        ->where('event_name', 'test.explicit_phone')
        ->where('user_id', $userId)
        ->latest('created_at')
        ->first();

    $payload = json_decode((string) $row->payload, true);

    expect($payload['phone'])->toBe('+15551234567');
});
