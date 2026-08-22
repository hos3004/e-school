<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\AcademicReports\Domain\Enums\MonthlyReportStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * التقرير الشهري للطالب — مجمّع الحضور والدرجات والمخالفات لشهر واحد.
 *
 * المعرّفات الخارجية (organization_id, student_profile_id, enrollment_id)
 * أعمدة عادية فقط — نماذجها مملوكة لموديولات أخرى ولا تُستورد هنا.
 */
final class MonthlyReport extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'monthly_reports';

    protected $fillable = [
        'organization_id',
        'student_profile_id',
        'enrollment_id',
        'period_year',
        'period_month',
        'metrics',
        'supervisor_summary',
        'status',
        'approved_by',
        'approved_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'period_year' => 'int',
            'period_month' => 'int',
            'metrics' => 'array',
            'supervisor_summary' => 'string',
            'status' => MonthlyReportStatus::class,
            'approved_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
        ];
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForStudent(Builder $query, string $studentProfileId): Builder
    {
        return $query->where('student_profile_id', $studentProfileId);
    }

    public function scopeInPeriod(Builder $query, int $year, int $month): Builder
    {
        return $query->where('period_year', $year)->where('period_month', $month);
    }

    public function hasStatus(MonthlyReportStatus $status): bool
    {
        return $this->status === $status;
    }
}
