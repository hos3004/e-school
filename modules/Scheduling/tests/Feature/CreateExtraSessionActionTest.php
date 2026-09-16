<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Groups\Domain\Models\GroupMembership;
use Modules\Scheduling\Application\Actions\CreateExtraSessionAction;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('creates an individual extra session outside any recurring schedule without approval', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $fixture = schedulingFixture();

    $sessionId = app(CreateExtraSessionAction::class)->execute(
        organizationId: (string) $fixture['organization']->id,
        groupId: null,
        studentProfileId: (string) $fixture['student']->id,
        courseId: (string) $fixture['course']->id,
        staffProfileId: (string) $fixture['teacher']->id,
        startsAt: CarbonImmutable::now('UTC')->addDay(),
        durationMinutes: 30,
        payrollExempt: true,
        payrollRateOverrideMinorUnits: null,
        actorId: (string) $fixture['operator']->id,
        reason: 'طلب حصة قرآن فردي إضافية خارج الموعد الدائم.',
    );

    $session = Session::query()->findOrFail($sessionId);

    expect($session->status)->toBe(SessionStatus::Scheduled)
        ->and($session->session_type)->toBe('individual')
        ->and($session->schedule_id)->toBeNull()
        ->and($session->payroll_exempt)->toBeTrue()
        ->and($session->payroll_rate_override_minor_units)->toBeNull()
        ->and($session->participants()->where('student_profile_id', $fixture['student']->id)->exists())->toBeTrue();
});

it('creates a group extra session with a custom payroll price for the active members', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $fixture = schedulingFixture();

    $sessionId = app(CreateExtraSessionAction::class)->execute(
        organizationId: (string) $fixture['organization']->id,
        groupId: (string) $fixture['group']->id,
        studentProfileId: null,
        courseId: (string) $fixture['course']->id,
        staffProfileId: (string) $fixture['teacher']->id,
        startsAt: CarbonImmutable::now('UTC')->addDay(),
        durationMinutes: 45,
        payrollExempt: false,
        payrollRateOverrideMinorUnits: 5_000,
        actorId: (string) $fixture['operator']->id,
        reason: 'حصة مراجعة إضافية للمجموعة قبل الاختبار.',
    );

    $session = Session::query()->findOrFail($sessionId);

    expect($session->session_type)->toBe('group')
        ->and($session->group_id)->toBe((string) $fixture['group']->id)
        ->and($session->payroll_exempt)->toBeFalse()
        ->and($session->payroll_rate_override_minor_units)->toBe(5_000);
});

it('rejects an extra session that conflicts with the teacher existing booking', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $fixture = schedulingFixture();
    $start = CarbonImmutable::now('UTC')->addDay();

    app(CreateExtraSessionAction::class)->execute(
        organizationId: (string) $fixture['organization']->id,
        groupId: null,
        studentProfileId: (string) $fixture['student']->id,
        courseId: (string) $fixture['course']->id,
        staffProfileId: (string) $fixture['teacher']->id,
        startsAt: $start,
        durationMinutes: 30,
        payrollExempt: true,
        payrollRateOverrideMinorUnits: null,
        actorId: (string) $fixture['operator']->id,
        reason: 'الحصة الأولى.',
    );

    $execute = fn () => app(CreateExtraSessionAction::class)->execute(
        organizationId: (string) $fixture['organization']->id,
        groupId: null,
        studentProfileId: (string) $fixture['student']->id,
        courseId: (string) $fixture['course']->id,
        staffProfileId: (string) $fixture['teacher']->id,
        startsAt: $start->addMinutes(10),
        durationMinutes: 30,
        payrollExempt: true,
        payrollRateOverrideMinorUnits: null,
        actorId: (string) $fixture['operator']->id,
        reason: 'الحصة الثانية المتعارضة.',
    );

    expect($execute)->toThrow(BusinessRuleViolation::class);
});

it('rejects setting a custom price on a session exempt from payroll', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $fixture = schedulingFixture();

    $execute = fn () => app(CreateExtraSessionAction::class)->execute(
        organizationId: (string) $fixture['organization']->id,
        groupId: null,
        studentProfileId: (string) $fixture['student']->id,
        courseId: (string) $fixture['course']->id,
        staffProfileId: (string) $fixture['teacher']->id,
        startsAt: CarbonImmutable::now('UTC')->addDay(),
        durationMinutes: 30,
        payrollExempt: true,
        payrollRateOverrideMinorUnits: 1_000,
        actorId: (string) $fixture['operator']->id,
        reason: 'تعارض مقصود بين الإعفاء والسعر المخصّص.',
    );

    expect($execute)->toThrow(BusinessRuleViolation::class);
});

it('rejects an extra session that names neither a group nor a student', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $fixture = schedulingFixture();

    $execute = fn () => app(CreateExtraSessionAction::class)->execute(
        organizationId: (string) $fixture['organization']->id,
        groupId: null,
        studentProfileId: null,
        courseId: (string) $fixture['course']->id,
        staffProfileId: (string) $fixture['teacher']->id,
        startsAt: CarbonImmutable::now('UTC')->addDay(),
        durationMinutes: 30,
        payrollExempt: true,
        payrollRateOverrideMinorUnits: null,
        actorId: (string) $fixture['operator']->id,
        reason: 'بلا وجهة.',
    );

    expect($execute)->toThrow(BusinessRuleViolation::class);
});

it('rejects an extra session for a group with no active members', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $fixture = schedulingFixture();
    GroupMembership::query()
        ->where('group_id', $fixture['group']->id)
        ->update(['status' => MembershipStatus::Left, 'left_at' => now('UTC')]);

    $execute = fn () => app(CreateExtraSessionAction::class)->execute(
        organizationId: (string) $fixture['organization']->id,
        groupId: (string) $fixture['group']->id,
        studentProfileId: null,
        courseId: (string) $fixture['course']->id,
        staffProfileId: (string) $fixture['teacher']->id,
        startsAt: CarbonImmutable::now('UTC')->addDay(),
        durationMinutes: 30,
        payrollExempt: true,
        payrollRateOverrideMinorUnits: null,
        actorId: (string) $fixture['operator']->id,
        reason: 'مجموعة بلا طلاب نشطين.',
    );

    expect($execute)->toThrow(BusinessRuleViolation::class);
});
