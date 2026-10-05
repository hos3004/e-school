<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Reporting\Domain\Contracts\ProgramDigestRecipientSettings;
use Modules\Reporting\Domain\Enums\DigestRecipientType;
use Shared\Testing\Fixtures;

it('has no setting configured before the first save', function (): void {
    $organizationId = Fixtures::organizationId();

    expect(app(ProgramDigestRecipientSettings::class)->current($organizationId))->toBeNull();
});

it('saves a custom email recipient and records an audit entry', function (): void {
    $organizationId = Fixtures::organizationId();
    $actorId = Fixtures::userId();

    $saved = app(ProgramDigestRecipientSettings::class)->saveGlobal(
        $organizationId,
        DigestRecipientType::CustomEmail->value,
        null,
        'principal@school-mail.example.org',
        $actorId,
        'إعداد أولي لمستلم التقرير الشهري',
        null,
    );

    expect($saved->recipientType)->toBe(DigestRecipientType::CustomEmail->value)
        ->and($saved->customEmail)->toBe('principal@school-mail.example.org');

    expect(app(ProgramDigestRecipientSettings::class)->resolveEmail($organizationId))->toBe('principal@school-mail.example.org');

    $audit = DB::table('audit_log')
        ->where('organization_id', $organizationId)
        ->where('action', 'reporting.settings.program_digest_recipient')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->actor_id)->toBe($actorId)
        ->and($audit->reason)->toBe('إعداد أولي لمستلم التقرير الشهري');
});

it('rejects a custom email on a reserved undeliverable domain', function (): void {
    $organizationId = Fixtures::organizationId();

    app(ProgramDigestRecipientSettings::class)->saveGlobal(
        $organizationId,
        DigestRecipientType::CustomEmail->value,
        null,
        'someone@example.invalid',
        Fixtures::userId(),
        'محاولة إعداد بريد وهمي',
        null,
    );
})->throws(ValidationException::class);

it('saves an existing staff/user recipient by user id', function (): void {
    $organizationId = Fixtures::organizationId();
    $userId = (string) Str::ulid();
    DB::table('users')->insert([
        'id' => $userId,
        'organization_id' => $organizationId,
        'name' => 'Staff Member',
        'email' => 'staff.member@school-mail.net',
        'profile_completed_at' => now(),
        'password' => bcrypt('secret'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $saved = app(ProgramDigestRecipientSettings::class)->saveGlobal(
        $organizationId,
        DigestRecipientType::StaffEmail->value,
        $userId,
        null,
        Fixtures::userId(),
        'اختيار مستخدم موجود',
        null,
    );

    expect($saved->recipientUserId)->toBe($userId);
    expect(app(ProgramDigestRecipientSettings::class)->resolveEmail($organizationId))->toBe('staff.member@school-mail.net');
});

it('rejects a save with a stale version (optimistic concurrency)', function (): void {
    $organizationId = Fixtures::organizationId();
    $settings = app(ProgramDigestRecipientSettings::class);

    $first = $settings->saveGlobal($organizationId, DigestRecipientType::CustomEmail->value, null, 'a@school-mail.net', Fixtures::userId(), 'أول حفظ', null);

    // تعديل ثانٍ ناجح يُغيّر النسخة.
    $settings->saveGlobal($organizationId, DigestRecipientType::CustomEmail->value, null, 'b@school-mail.net', Fixtures::userId(), 'تعديل ثانٍ', $first->version);

    // محاولة استخدام النسخة القديمة بعد أن تغيّرت فعلًا.
    $settings->saveGlobal($organizationId, DigestRecipientType::CustomEmail->value, null, 'c@school-mail.net', Fixtures::userId(), 'تعارض متزامن', $first->version);
})->throws(ValidationException::class);

it('replaces the single global row instead of creating a second one', function (): void {
    $organizationId = Fixtures::organizationId();
    $settings = app(ProgramDigestRecipientSettings::class);

    $settings->saveGlobal($organizationId, DigestRecipientType::CustomEmail->value, null, 'first@school-mail.net', Fixtures::userId(), 'حفظ أول', null);
    $settings->saveGlobal($organizationId, DigestRecipientType::CustomEmail->value, null, 'second@school-mail.net', Fixtures::userId(), 'حفظ ثانٍ', null);

    $count = DB::table('reporting_program_digest_recipients')
        ->where('organization_id', $organizationId)
        ->whereNull('program_id')
        ->count();

    expect($count)->toBe(1)
        ->and($settings->current($organizationId)->customEmail)->toBe('second@school-mail.net');
});
