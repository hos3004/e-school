<?php

declare(strict_types=1);

namespace Modules\Reporting\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Reporting\Application\Actions\RecordOrganizationSnapshotAction;
use Modules\Reporting\Domain\Models\StudentDashboard;
use Modules\Reporting\Domain\Models\TeacherDashboard;
use Shared\Testing\Fixtures;

/**
 * بيانات تجريبية للوحات Reporting.
 *
 * اللوحات Read Models تُبنى عادة من أحداث الموديولات الأخرى؛ هذا البذر
 * يقرأ التسجيلات وملفات الموظفين الموجودة فعليًا ويبني لوحات أولية
 * معقولة، ثم يسجّل لقطة تنظيمية لليوم. إن لم توجد بيانات المصدر
 * يكتفي بما يملكه Fixtures.
 */
final class ReportingSeeder extends Seeder
{
    /** سقف عرض البيانات التجريبية — ليس رقم سياسة عمل. */
    private const DEMO_LIMIT = 12;

    public function run(): void
    {
        $organizationId = Fixtures::organizationId();

        $this->seedStudentDashboards($organizationId);
        $this->seedTeacherDashboards($organizationId);
        $this->seedTodaySnapshot($organizationId);
    }

    private function seedStudentDashboards(string $organizationId): void
    {
        if (!DB::getSchemaBuilder()->hasTable('enrollments')) {
            return;
        }

        $enrollments = DB::table('enrollments')
            ->where('organization_id', $organizationId)
            ->orderBy('created_at')
            ->limit(self::DEMO_LIMIT)
            ->get(['id', 'student_profile_id']);

        foreach ($enrollments as $index => $enrollment) {
            StudentDashboard::query()->firstOrCreate(
                ['enrollment_id' => (string) $enrollment->id],
                [
                    'organization_id' => $organizationId,
                    'student_profile_id' => (string) $enrollment->student_profile_id,
                    'sessions_total' => 10 + $index,
                    'sessions_attended' => 8 + $index % 3,
                    'sessions_missed' => max(0, 2 - $index % 3),
                    // نُبقي القيمتين متسقتين ثم نعيد حساب النسبة.
                    'violations_count' => $index % 4 === 0 ? 1 : 0,
                    'freezes_count' => $index % 5 === 0 ? 1 : 0,
                    'last_session_at' => CarbonImmutable::now('UTC')->subDays($index % 7),
                    'last_violation_at' => $index % 4 === 0 ? CarbonImmutable::now('UTC')->subDays(2) : null,
                ],
            );

            /** @var StudentDashboard|null $dashboard */
            $dashboard = StudentDashboard::query()->where('enrollment_id', (string) $enrollment->id)->first();

            $dashboard?->recomputeAttendanceRate();
            $dashboard?->save();
        }
    }

    private function seedTeacherDashboards(string $organizationId): void
    {
        if (!DB::getSchemaBuilder()->hasTable('staff_profiles')) {
            return;
        }

        $staff = DB::table('staff_profiles')
            ->where('organization_id', $organizationId)
            ->orderBy('created_at')
            ->limit(self::DEMO_LIMIT)
            ->pluck('id');

        foreach ($staff as $index => $staffProfileId) {
            TeacherDashboard::query()->firstOrCreate(
                ['staff_profile_id' => (string) $staffProfileId],
                [
                    'organization_id' => $organizationId,
                    'sessions_total' => 20 + $index,
                    'sessions_completed' => 15 + $index,
                    'cancellations_by_self' => $index % 3,
                    'postponements' => $index % 4,
                    // بالقروش — 150 جنيًا للحصة المكتملة كمتوسط تجريبي فقط.
                    'payout_minor' => (15 + $index) * 15000,
                    'currency' => 'EGP',
                    'last_session_at' => CarbonImmutable::now('UTC')->subDays($index % 5),
                ],
            );
        }
    }

    private function seedTodaySnapshot(string $organizationId): void
    {
        app(RecordOrganizationSnapshotAction::class)->execute($organizationId, [
            'snapshot_date' => CarbonImmutable::now('UTC')->toDateString(),
            'students_active' => StudentDashboard::query()
                ->forOrganization($organizationId)
                ->count(),
            'students_frozen' => 0,
            'teachers_active' => TeacherDashboard::query()
                ->forOrganization($organizationId)
                ->count(),
            'sessions_held' => (int) StudentDashboard::query()
                ->forOrganization($organizationId)
                ->sum('sessions_attended'),
            'sessions_cancelled' => (int) StudentDashboard::query()
                ->forOrganization($organizationId)
                ->sum('sessions_missed'),
        ]);
    }
}
