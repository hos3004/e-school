<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * لوحة الطالب — Read Model مسطّح لكل تسجيل.
 *
 * يُحدَّث من أحداث الموديولات الأخرى عبر مستمعي الإسقاط، ولا يحمل
 * أي علاقة Eloquent لنماذج خارج الموديول — المعرّفات الخارجية أعمدة فقط.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $enrollment_id
 * @property string $student_profile_id
 * @property int $sessions_total
 * @property int $sessions_attended
 * @property int $sessions_missed
 * @property int $attendance_rate_bp
 * @property int $violations_count
 * @property int $freezes_count
 * @property CarbonImmutable|null $last_session_at
 * @property CarbonImmutable|null $last_violation_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
final class StudentDashboard extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'report_student_dashboards';

    protected $fillable = [
        'organization_id',
        'enrollment_id',
        'student_profile_id',
        'sessions_total',
        'sessions_attended',
        'sessions_missed',
        'attendance_rate_bp',
        'violations_count',
        'freezes_count',
        'last_session_at',
        'last_violation_at',
    ];

    protected function casts(): array
    {
        return [
            'sessions_total' => 'integer',
            'sessions_attended' => 'integer',
            'sessions_missed' => 'integer',
            'attendance_rate_bp' => 'integer',
            'violations_count' => 'integer',
            'freezes_count' => 'integer',
            'last_session_at' => 'immutable_datetime',
            'last_violation_at' => 'immutable_datetime',
        ];
    }

    /**
     * نسبة الحضور من أصل 10000.
     */
    public function recomputeAttendanceRate(): void
    {
        $denominator = (int) $this->sessions_attended + (int) $this->sessions_missed;

        $this->attendance_rate_bp = $denominator === 0
            ? 0
            : (int) round(((int) $this->sessions_attended * 10000) / $denominator);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForStudent(Builder $query, string $studentProfileId): Builder
    {
        return $query->where('student_profile_id', $studentProfileId);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeAtRisk(Builder $query, int $maxRateBp): Builder
    {
        return $query->where('attendance_rate_bp', '<', $maxRateBp);
    }
}
