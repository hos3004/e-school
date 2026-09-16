<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Reporting\Application\Services\MonthlyProgramDigestSender;
use Modules\Reporting\Domain\Contracts\ProgramDigestRecipientSettings;
use Modules\Reporting\Domain\Enums\DigestRecipientType;
use Modules\Reporting\Infrastructure\Mail\MonthlyProgramDigestMail;
use Modules\Reporting\Tests\Support\DigestSessionContext;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

it('sends nothing when no recipient is configured, and never touches the real transport', function (): void {
    Mail::fake();

    $context = DigestSessionContext::create(scheduledStart: CarbonImmutable::parse('2026-03-10 10:00:00', 'UTC'));
    DigestSessionContext::submitReport($context['session_id'], $context['staff_profile_id'], Fixtures::studentProfileId(), CarbonImmutable::parse('2026-03-10 10:00:00', 'UTC'));

    $sent = app(MonthlyProgramDigestSender::class)->send(
        $context['organization_id'],
        CarbonImmutable::parse('2026-03-01 00:00:00', 'UTC'),
        CarbonImmutable::parse('2026-04-01 00:00:00', 'UTC'),
        '2026-03-01',
        '2026-03-31',
        'ar',
    );

    expect($sent)->toBe(0);
    Mail::assertNothingSent();
});

it('sends exactly one digest email per program to the configured recipient, never to a real address by accident', function (): void {
    Mail::fake();

    $context = DigestSessionContext::create(scheduledStart: CarbonImmutable::parse('2026-03-10 10:00:00', 'UTC'));
    $studentId = Fixtures::studentProfileId();
    DigestSessionContext::submitReport($context['session_id'], $context['staff_profile_id'], $studentId, CarbonImmutable::parse('2026-03-10 10:00:00', 'UTC'));

    app(ProgramDigestRecipientSettings::class)->saveGlobal(
        $context['organization_id'],
        DigestRecipientType::CustomEmail->value,
        null,
        'owner@school-mail.net',
        Fixtures::userId(),
        'ضبط مستلم الاختبار',
        null,
    );

    $sent = app(MonthlyProgramDigestSender::class)->send(
        $context['organization_id'],
        CarbonImmutable::parse('2026-03-01 00:00:00', 'UTC'),
        CarbonImmutable::parse('2026-04-01 00:00:00', 'UTC'),
        '2026-03-01',
        '2026-03-31',
        'ar',
    );

    expect($sent)->toBe(1);

    Mail::assertSent(MonthlyProgramDigestMail::class, function (MonthlyProgramDigestMail $mail) {
        return $mail->hasTo('owner@school-mail.net')
            && $mail->periodFromLabel === '2026-03-01'
            && $mail->entriesByStudentName !== [];
    });
    Mail::assertSentCount(1);
});

it('sends one separate email per program when reports span more than one program', function (): void {
    Mail::fake();

    $first = DigestSessionContext::create(scheduledStart: CarbonImmutable::parse('2026-03-05 08:00:00', 'UTC'));
    DigestSessionContext::submitReport($first['session_id'], $first['staff_profile_id'], Fixtures::studentProfileId(), CarbonImmutable::parse('2026-03-05 08:00:00', 'UTC'));

    $second = DigestSessionContext::create(scheduledStart: CarbonImmutable::parse('2026-03-06 08:00:00', 'UTC'), organizationId: $first['organization_id']);
    DigestSessionContext::submitReport($second['session_id'], $second['staff_profile_id'], Fixtures::studentProfileId(), CarbonImmutable::parse('2026-03-06 08:00:00', 'UTC'));

    expect($first['program_id'])->not->toBe($second['program_id']);

    app(ProgramDigestRecipientSettings::class)->saveGlobal(
        $first['organization_id'], DigestRecipientType::CustomEmail->value, null, 'owner2@school-mail.net', Fixtures::userId(), 'ضبط', null,
    );

    $sent = app(MonthlyProgramDigestSender::class)->send(
        $first['organization_id'],
        CarbonImmutable::parse('2026-03-01 00:00:00', 'UTC'),
        CarbonImmutable::parse('2026-04-01 00:00:00', 'UTC'),
        '2026-03-01', '2026-03-31', 'ar',
    );

    expect($sent)->toBe(2);
    Mail::assertSentCount(2);
});
