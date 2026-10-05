<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AcademicReports\Database\Factories\SessionReportFactory;
use Modules\AcademicReports\Domain\Contracts\SessionReportBatchQueries;
use Shared\Testing\Fixtures;

/**
 * @param array<string, mixed> $overrides
 */
function makeSessionReportRow(string $sessionId, string $staffProfileId, ?string $submittedAt, array $overrides = []): string
{
    $reportId = (string) Str::ulid();

    DB::table('session_reports')->insert(array_merge([
        'id' => $reportId,
        'session_id' => $sessionId,
        'staff_profile_id' => $staffProfileId,
        'topics_covered' => 'الجمع والطرح',
        'homework_assigned' => 'تمارين ١-٥',
        'general_notes' => 'ملاحظة عامة',
        'supervisor_private_note' => 'ملاحظة إشرافية سرية',
        'next_session_plan' => 'مراجعة',
        'submitted_at' => $submittedAt,
        'is_late' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return $reportId;
}

it('returns only submitted reports with student rows and never leaks the supervisor private note', function (): void {
    $submitted = SessionReportFactory::createSessionContext();
    $unsubmitted = SessionReportFactory::createSessionContext();

    $submittedReportId = makeSessionReportRow($submitted['session_id'], $submitted['staff_profile_id'], now()->toIso8601String());
    makeSessionReportRow($unsubmitted['session_id'], $unsubmitted['staff_profile_id'], null);

    $studentId = Fixtures::studentProfileId();
    DB::table('session_report_students')->insert([
        'id' => (string) Str::ulid(),
        'session_report_id' => $submittedReportId,
        'student_profile_id' => $studentId,
        'participation' => 4,
        'performance' => 5,
        'commitment' => 3,
        'strengths' => 'يشارك كثيرًا',
        'weaknesses' => 'يحتاج تركيزًا',
        'note' => 'ملاحظة عن الطالب',
    ]);

    $rows = app(SessionReportBatchQueries::class)->forSessions([$submitted['session_id'], $unsubmitted['session_id']]);

    expect($rows)->toHaveCount(1);
    $report = $rows[0];
    expect($report->sessionId)->toBe($submitted['session_id'])
        ->and($report->topicsCovered)->toBe('الجمع والطرح')
        ->and($report->students)->toHaveCount(1);

    $student = $report->students[0];
    expect($student->studentProfileId)->toBe($studentId)
        ->and($student->participation)->toBe(4);

    // القسم الأهم: الملاحظة الإشرافية الخاصة يجب ألا تظهر في أي حقل من حقول الـDTO.
    $serialized = json_encode($report) ?: '';
    expect($serialized)->not->toContain('ملاحظة إشرافية سرية');
});

it('returns an empty list for an empty or unknown session id list', function (): void {
    expect(app(SessionReportBatchQueries::class)->forSessions([]))->toBe([]);
    expect(app(SessionReportBatchQueries::class)->forSessions([(string) Str::ulid()]))->toBe([]);
});
