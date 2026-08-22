<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AcademicReports\Domain\Enums\MonthlyReportStatus;
use Modules\AcademicReports\Domain\Models\MonthlyReport;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<MonthlyReport>
 */
final class MonthlyReportFactory extends Factory
{
    protected $model = MonthlyReport::class;

    public function definition(): array
    {
        return [
            'organization_id' => Fixtures::organizationId(),
            'student_profile_id' => Fixtures::studentProfileId(),
            'enrollment_id' => $this->existingEnrollmentId(),
            'period_year' => (int) now('UTC')->year,
            'period_month' => (int) now('UTC')->month,
            'metrics' => [
                'attendance_present_sessions' => $this->faker->numberBetween(4, 12),
                'session_reports_count' => $this->faker->numberBetween(4, 12),
            ],
            'supervisor_summary' => $this->faker->sentence(),
            'status' => MonthlyReportStatus::Draft,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => MonthlyReportStatus::Approved]);
    }

    /**
     * تسجيل تجريبي موجود فعلًا — يُنشأ مباشرة عبر DB لأن موديول
     * Enrollments لا يجوز استيراد نماذجه عبر الحدود.
     */
    private function existingEnrollmentId(): string
    {
        $existing = DB::table('enrollments')->whereNull('deleted_at')->value('id');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $organizationId = Fixtures::organizationId();
        $programId = (string) Str::ulid();

        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organizationId,
            'code' => 'PRG-'.strtoupper(substr($programId, -8)),
            'name' => json_encode(['ar' => 'برنامج الاختبار', 'en' => 'Test Program'], JSON_UNESCAPED_UNICODE),
            'default_session_minutes' => 60,
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $enrollmentId = (string) Str::ulid();

        DB::table('enrollments')->insert([
            'id' => $enrollmentId,
            'organization_id' => $organizationId,
            'student_profile_id' => Fixtures::studentProfileId(),
            'program_id' => $programId,
            'status' => 'active',
            'applied_at' => now(),
            'activated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $enrollmentId;
    }
}
