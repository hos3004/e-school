<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\AcademicReports\Application\Actions\ApproveMonthlyReportAction;
use Modules\AcademicReports\Application\Actions\DraftMonthlyReportAction;
use Modules\AcademicReports\Application\Actions\SubmitSessionReportAction;
use Shared\Support\BusinessRuleViolation;

/**
 * بيانات تجريبية للتقارير الأكاديمية.
 *
 * لا ينشئ بيانات موديول آخر: تقارير الحصص تُبنى على حصص وطلاب موجودين،
 * والتقارير الشهرية على تسجيلات موجودة — وإن لم توجد يكتفي بالتحذير.
 */
final class AcademicReportsSeeder extends Seeder
{
    /** أقصى عدد تقارير تجريبية — سقف عرض فقط وليس رقم سياسة عمل. */
    private const DEMO_LIMIT = 5;

    public function run(): void
    {
        $this->seedSessionReports();
        $this->seedMonthlyReports();
    }

    private function seedSessionReports(): void
    {
        $sessions = DB::table('sessions')
            ->where('status', 'completed')
            ->orderBy('created_at')
            ->limit(self::DEMO_LIMIT)
            ->get(['id', 'staff_profile_id']);

        $studentIds = DB::table('student_profiles')->limit(3)->pluck('id');

        if ($sessions->isEmpty() || $studentIds->isEmpty()) {
            $this->command?->warn(__('academicreports::messages.seeder_no_sessions'));

            return;
        }

        $action = app(SubmitSessionReportAction::class);

        foreach ($sessions as $index => $session) {
            try {
                $action->execute(
                    sessionId: (string) $session->id,
                    staffProfileId: (string) $session->staff_profile_id,
                    students: [
                        [
                            'student_profile_id' => (string) $studentIds[$index % $studentIds->count()],
                            'participation' => 3 + ($index % 3),
                            'performance' => 2 + ($index % 4),
                            'commitment' => 3 + (($index + 1) % 3),
                            'strengths' => __('academicreports::messages.demo_strengths'),
                            'weaknesses' => __('academicreports::messages.demo_weaknesses'),
                        ],
                    ],
                    topicsCovered: __('academicreports::messages.demo_topics', ['n' => $index + 1]),
                );
            } catch (BusinessRuleViolation) {
                // الحصة لها تقرير سابق — نتخطاها حفاظًا على الفرادة.
                continue;
            }
        }
    }

    private function seedMonthlyReports(): void
    {
        $enrollments = DB::table('enrollments')
            ->whereNull('deleted_at')
            ->limit(self::DEMO_LIMIT)
            ->get(['id', 'organization_id', 'student_profile_id']);

        if ($enrollments->isEmpty()) {
            $this->command?->warn(__('academicreports::messages.seeder_no_enrollments'));

            return;
        }

        $draft = app(DraftMonthlyReportAction::class);
        $approve = app(ApproveMonthlyReportAction::class);

        $approverId = DB::table('users')->orderBy('created_at')->value('id');

        foreach ($enrollments as $index => $enrollment) {
            try {
                $report = $draft->execute(
                    organizationId: (string) $enrollment->organization_id,
                    studentProfileId: (string) $enrollment->student_profile_id,
                    enrollmentId: (string) $enrollment->id,
                    periodYear: (int) now('UTC')->subMonths(1)->year,
                    periodMonth: (int) now('UTC')->subMonths(1)->month,
                    metrics: [
                        'attendance_present_sessions' => max(8 - $index, 0),
                        'session_reports_count' => max(10 - $index, 0),
                    ],
                    supervisorSummary: __('academicreports::messages.demo_summary'),
                );

                // النصف الأول يُعتمد ليبين الفرق بين الحالات في اللوحة.
                if ($index % 2 === 0 && is_string($approverId) && $approverId !== '') {
                    $approve->execute(
                        report: $report,
                        approverId: $approverId,
                        reason: __('academicreports::messages.demo_approval_reason'),
                    );
                }
            } catch (BusinessRuleViolation) {
                // تقرير موجود مسبقًا لنفس الفترة — نتخطاه حفاظًا على الفرادة.
                continue;
            }
        }
    }
}
