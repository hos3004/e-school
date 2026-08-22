<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\AcademicReports\Application\Actions\ApproveMonthlyReportAction;
use Modules\AcademicReports\Application\Actions\DraftMonthlyReportAction;
use Modules\AcademicReports\Application\Actions\SendMonthlyReportAction;
use Modules\AcademicReports\Database\Factories\MonthlyReportFactory;
use Modules\AcademicReports\Domain\Enums\MonthlyReportStatus;
use Modules\AcademicReports\Domain\Events\MonthlyReportApproved;
use Modules\AcademicReports\Domain\Events\MonthlyReportDrafted;
use Modules\AcademicReports\Domain\Events\MonthlyReportSent;
use Modules\AcademicReports\Domain\Models\MonthlyReport;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

/*
| اختبارات دورة حياة التقرير الشهري: توليد ← اعتماد ← إرسال.
*/

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->draft = app(DraftMonthlyReportAction::class);
    $this->approve = app(ApproveMonthlyReportAction::class);
    $this->send = app(SendMonthlyReportAction::class);
});

it('drafts a monthly report and publishes the event', function (): void {
    Event::fake([MonthlyReportDrafted::class]);

    $report = $this->draft->execute(
        organizationId: Fixtures::organizationId(),
        studentProfileId: Fixtures::studentProfileId(),
        enrollmentId: resolveEnrollmentId(),
        periodYear: 2026,
        periodMonth: 5,
        metrics: ['attendance_present_sessions' => 9],
    );

    expect($report->status)->toBe(MonthlyReportStatus::Draft)
        ->and($report->period_year)->toBe(2026)
        ->and($report->period_month)->toBe(5)
        ->and($report->metrics)->toBe(['attendance_present_sessions' => 9]);

    Event::assertDispatched(
        MonthlyReportDrafted::class,
        fn (MonthlyReportDrafted $event): bool => $event->monthlyReportId === $report->id
            && $event->periodYear === 2026
            && $event->periodMonth === 5,
    );
});

it('rejects a duplicate report for the same student and period', function (): void {
    $args = [
        'organizationId' => Fixtures::organizationId(),
        'studentProfileId' => Fixtures::studentProfileId(),
        'enrollmentId' => resolveEnrollmentId(),
        'periodYear' => 2026,
        'periodMonth' => 3,
    ];

    $this->draft->execute(...$args);

    try {
        $this->draft->execute(...$args);
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('academicreports.monthly_report.duplicate_period');
    }
});

it('approves a draft and stamps approver and time', function (): void {
    Event::fake([MonthlyReportApproved::class]);

    $report = createDraftReport();

    $approved = $this->approve->execute(
        report: $report,
        approverId: Fixtures::userId(),
        reason: 'مراجعة المشرف واعتماد المؤشرات',
    );

    expect($approved->status)->toBe(MonthlyReportStatus::Approved)
        ->and($approved->approved_by)->not->toBeNull()
        ->and($approved->approved_at)->toBeInstanceOf(CarbonImmutable::class);

    Event::assertDispatched(MonthlyReportApproved::class);
});

it('rejects approving a non-draft report', function (): void {
    $report = createDraftReport();
    $this->approve->execute($report, Fixtures::userId(), 'سبب أول');

    $this->approve->execute($report, Fixtures::userId(), 'محاولة ثانية');
})->throws(BusinessRuleViolation::class);

it('sends an approved report and stamps the sent time', function (): void {
    Event::fake([MonthlyReportSent::class]);

    $report = createDraftReport();
    $approved = $this->approve->execute($report, Fixtures::userId(), 'سبب الاعتماد');
    $sent = $this->send->execute($approved);

    expect($sent->status)->toBe(MonthlyReportStatus::Sent)
        ->and($sent->sent_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($sent->status->isTerminal())->toBeTrue();

    Event::assertDispatched(
        MonthlyReportSent::class,
        fn (MonthlyReportSent $event): bool => $event->monthlyReportId === $sent->id,
    );
});

it('rejects sending a draft report', function (): void {
    $report = createDraftReport();

    $this->send->execute($report);
})->throws(BusinessRuleViolation::class);

/**
 * تسجيل موجود للاختبار — يُنشأ مباشرة عبر DB كما في Fixtures.
 */
function resolveEnrollmentId(): string
{
    $existing = DB::table('enrollments')->whereNull('deleted_at')->value('id');

    if (is_string($existing) && $existing !== '') {
        return $existing;
    }

    return (string) MonthlyReportFactory::new()->create()->enrollment_id;
}

function createDraftReport(): MonthlyReport
{
    return app(DraftMonthlyReportAction::class)->execute(
        organizationId: Fixtures::organizationId(),
        studentProfileId: Fixtures::studentProfileId(),
        enrollmentId: resolveEnrollmentId(),
        periodYear: 2026,
        periodMonth: 7,
    );
}
