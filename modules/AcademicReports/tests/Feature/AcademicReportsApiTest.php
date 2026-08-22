<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\AcademicReports\Application\Actions\DraftMonthlyReportAction;
use Modules\AcademicReports\Database\Factories\MonthlyReportFactory;
use Modules\AcademicReports\Database\Factories\SessionReportFactory;
use Modules\AcademicReports\Domain\Enums\MonthlyReportStatus;
use Modules\AcademicReports\Domain\Events\MonthlyReportSent;
use Modules\AcademicReports\Domain\Events\SessionReportSubmitted;
use Modules\AcademicReports\Domain\Models\MonthlyReport;
use Modules\AcademicReports\Tests\Support\ApiUser;
use Shared\Testing\Fixtures;
use Tests\TestCase;

/**
 * مسارات موديول AcademicReports:
 *  POST   /api/session-reports
 *  GET    /api/monthly-reports
 *  PATCH  /api/monthly-reports/{report}/approve
 *  PATCH  /api/monthly-reports/{report}/send
 */
final class AcademicReportsApiTest extends TestCase
{
    use RefreshDatabase;

    private const ACTOR_ID = '01ACTORACADEMICREP00000000';

    public function test_submits_session_report_through_the_api(): void
    {
        Event::fake([SessionReportSubmitted::class]);
        Gate::define('academicreports.session_report.create', fn (): bool => true);

        $studentProfileId = Fixtures::studentProfileId();
        $context = SessionReportFactory::createSessionContext();

        $response = $this->actingAs(new ApiUser(self::ACTOR_ID))
            ->postJson('/api/session-reports', [
                'session_id' => $context['session_id'],
                'staff_profile_id' => $context['staff_profile_id'],
                'topics_covered' => 'مراجعة الوحدة الأولى',
                'students' => [
                    [
                        'student_profile_id' => $studentProfileId,
                        'participation' => 4,
                        'performance' => 5,
                        'commitment' => 3,
                    ],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.is_late', false);

        $this->assertDatabaseCount('session_report_students', 1);

        Event::assertDispatched(SessionReportSubmitted::class);
    }

    public function test_rejects_out_of_scale_scores_with_validation_errors(): void
    {
        Gate::define('academicreports.session_report.create', fn (): bool => true);

        $context = SessionReportFactory::createSessionContext();

        $this->actingAs(new ApiUser(self::ACTOR_ID))
            ->postJson('/api/session-reports', [
                'session_id' => $context['session_id'],
                'staff_profile_id' => $context['staff_profile_id'],
                'students' => [
                    [
                        'student_profile_id' => Fixtures::studentProfileId(),
                        'participation' => 9,
                        'performance' => 5,
                        'commitment' => 3,
                    ],
                ],
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['students.0.participation']);
    }

    public function test_forbids_submission_without_the_ability(): void
    {
        Gate::define('academicreports.session_report.create', fn (): bool => false);

        $context = SessionReportFactory::createSessionContext();

        $this->actingAs(new ApiUser(self::ACTOR_ID))
            ->postJson('/api/session-reports', [
                'session_id' => $context['session_id'],
                'staff_profile_id' => $context['staff_profile_id'],
                'students' => [
                    [
                        'student_profile_id' => Fixtures::studentProfileId(),
                        'participation' => 4,
                        'performance' => 4,
                        'commitment' => 4,
                    ],
                ],
            ])->assertForbidden();
    }

    public function test_lists_monthly_reports_scoped_to_the_user_organization(): void
    {
        Gate::define('academicreports.monthly_report.view_any', fn (): bool => true);

        $organizationId = Fixtures::organizationId();

        MonthlyReport::query()->create([
            'organization_id' => $organizationId,
            'student_profile_id' => Fixtures::studentProfileId(),
            'enrollment_id' => $this->createEnrollment($organizationId),
            'period_year' => 2026,
            'period_month' => 4,
            'metrics' => [],
            'status' => MonthlyReportStatus::Draft,
        ]);

        $response = $this->actingAs(new ApiUser(self::ACTOR_ID, $organizationId))
            ->getJson('/api/monthly-reports');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.period_year', 2026)
            ->assertJsonPath('data.0.status.value', 'draft');
    }

    public function test_approves_then_sends_a_monthly_report_through_the_api(): void
    {
        Event::fake([MonthlyReportSent::class]);
        Gate::define('academicreports.monthly_report.approve', fn (): bool => true);
        Gate::define('academicreports.monthly_report.send', fn (): bool => true);

        $organizationId = Fixtures::organizationId();
        $actorId = Fixtures::userId();
        $enrollmentId = $this->createEnrollment($organizationId);

        /** @var MonthlyReport $report */
        $report = app(DraftMonthlyReportAction::class)->execute(
            organizationId: $organizationId,
            studentProfileId: Fixtures::studentProfileId(),
            enrollmentId: $enrollmentId,
            periodYear: 2026,
            periodMonth: 2,
        );

        $this->actingAs(new ApiUser($actorId, $organizationId))
            ->patchJson("/api/monthly-reports/{$report->id}/approve", [
                'reason' => 'اعتماد المشرف بعد المراجعة',
            ])->assertOk()
            ->assertJsonPath('data.status.value', 'approved');

        $this->actingAs(new ApiUser($actorId, $organizationId))
            ->patchJson("/api/monthly-reports/{$report->id}/send")
            ->assertOk()
            ->assertJsonPath('data.status.value', 'sent');

        expect(MonthlyReport::query()->find($report->id)?->sent_at)->not->toBeNull();

        Event::assertDispatched(MonthlyReportSent::class);
    }

    public function test_approval_requires_a_reason(): void
    {
        Gate::define('academicreports.monthly_report.approve', fn (): bool => true);

        $organizationId = Fixtures::organizationId();

        /** @var MonthlyReport $report */
        $report = app(DraftMonthlyReportAction::class)->execute(
            organizationId: $organizationId,
            studentProfileId: Fixtures::studentProfileId(),
            enrollmentId: $this->createEnrollment($organizationId),
            periodYear: 2026,
            periodMonth: 1,
        );

        $this->actingAs(new ApiUser(self::ACTOR_ID, $organizationId))
            ->patchJson("/api/monthly-reports/{$report->id}/approve", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    private function createEnrollment(string $organizationId): string
    {
        $existing = DB::table('enrollments')->whereNull('deleted_at')->value('id');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        return (string) MonthlyReportFactory::new()
            ->make(['organization_id' => $organizationId])
            ->enrollment_id;
    }
}
