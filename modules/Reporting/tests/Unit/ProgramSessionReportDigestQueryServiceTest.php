<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Reporting\Domain\Contracts\ProgramSessionReportDigestQueries;
use Modules\Reporting\Tests\Support\DigestSessionContext;
use Shared\Testing\Fixtures;

it('resolves the program via course->level->program for an individual session with no group', function (): void {
    $inMonth = CarbonImmutable::parse('2026-03-15 10:00:00', 'UTC');
    $context = DigestSessionContext::create(scheduledStart: $inMonth, groupId: null);
    $studentId = Fixtures::studentProfileId();
    DigestSessionContext::submitReport($context['session_id'], $context['staff_profile_id'], $studentId, $inMonth);

    $rows = app(ProgramSessionReportDigestQueries::class)->forOrganizationInRange(
        $context['organization_id'],
        CarbonImmutable::parse('2026-03-01 00:00:00', 'UTC'),
        CarbonImmutable::parse('2026-04-01 00:00:00', 'UTC'),
    );

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->programId)->toBe($context['program_id'])
        ->and($rows[0]->studentProfileId)->toBe($studentId);
});

it('excludes sessions outside the requested date range (month boundary)', function (): void {
    // DigestSessionContext::create يضبط scheduled_start قبل الوقت المُمرَّر بساعة واحدة (نهاية الحصة)،
    // لذا نُبعد اللحظة خارج النطاق بأكثر من ساعة كي يبقى scheduled_start فعليًا خارج شهر مارس.
    $lastDayOfMarch = CarbonImmutable::parse('2026-03-31 23:00:00', 'UTC');
    $firstDayOfApril = CarbonImmutable::parse('2026-04-01 02:00:00', 'UTC');

    $inRange = DigestSessionContext::create(scheduledStart: $lastDayOfMarch);
    DigestSessionContext::submitReport($inRange['session_id'], $inRange['staff_profile_id'], Fixtures::studentProfileId(), $lastDayOfMarch);

    $outOfRange = DigestSessionContext::create(
        scheduledStart: $firstDayOfApril,
        organizationId: $inRange['organization_id'],
        programId: $inRange['program_id'],
        courseId: $inRange['course_id'],
    );
    DigestSessionContext::submitReport($outOfRange['session_id'], $outOfRange['staff_profile_id'], Fixtures::studentProfileId(), $firstDayOfApril);

    $rows = app(ProgramSessionReportDigestQueries::class)->forOrganizationInRange(
        $inRange['organization_id'],
        CarbonImmutable::parse('2026-03-01 00:00:00', 'UTC'),
        CarbonImmutable::parse('2026-04-01 00:00:00', 'UTC'),
    );

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->sessionId)->toBe($inRange['session_id']);
});

it('never returns another organization session report (horizontal isolation)', function (): void {
    $orgA = DigestSessionContext::create(scheduledStart: CarbonImmutable::parse('2026-05-10 08:00:00', 'UTC'));
    DigestSessionContext::submitReport($orgA['session_id'], $orgA['staff_profile_id'], Fixtures::studentProfileId(), CarbonImmutable::parse('2026-05-10 08:00:00', 'UTC'));

    // مؤسسة أخرى منفصلة تمامًا.
    $otherOrgId = (string) Str::ulid();
    DB::table('organizations')->insert([
        'id' => $otherOrgId,
        'name' => json_encode(['ar' => 'مؤسسة أخرى', 'en' => 'Other Org'], JSON_UNESCAPED_UNICODE),
        'slug' => 'other-'.strtolower(substr($otherOrgId, -8)),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $orgB = DigestSessionContext::create(
        scheduledStart: CarbonImmutable::parse('2026-05-10 09:00:00', 'UTC'),
        organizationId: $otherOrgId,
    );
    DigestSessionContext::submitReport($orgB['session_id'], $orgB['staff_profile_id'], Fixtures::studentProfileId(), CarbonImmutable::parse('2026-05-10 09:00:00', 'UTC'));

    $rows = app(ProgramSessionReportDigestQueries::class)->forOrganizationInRange(
        $orgA['organization_id'],
        CarbonImmutable::parse('2026-05-01 00:00:00', 'UTC'),
        CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC'),
    );

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->sessionId)->toBe($orgA['session_id']);
});

it('excludes sessions that are not completed even if a report exists', function (): void {
    $cancelled = DigestSessionContext::create(scheduledStart: CarbonImmutable::parse('2026-06-05 08:00:00', 'UTC'), status: 'cancelled_by_school');
    DigestSessionContext::submitReport($cancelled['session_id'], $cancelled['staff_profile_id'], Fixtures::studentProfileId(), CarbonImmutable::parse('2026-06-05 08:00:00', 'UTC'));

    $rows = app(ProgramSessionReportDigestQueries::class)->forOrganizationInRange(
        $cancelled['organization_id'],
        CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC'),
        CarbonImmutable::parse('2026-07-01 00:00:00', 'UTC'),
    );

    expect($rows)->toBe([]);
});
