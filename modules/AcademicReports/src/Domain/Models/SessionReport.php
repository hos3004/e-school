<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * تقرير الحصة الذي يكتبه المعلم بعد انتهائها.
 *
 * session_id و staff_profile_id أعمدة عادية — نماذجهما في موديولات
 * أخرى ولا تُستورد هنا.
 *
 * @property string $session_id
 * @property string $staff_profile_id
 * @property CarbonImmutable|null $submitted_at
 * @property bool $is_late
 */
final class SessionReport extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'session_reports';

    protected $fillable = [
        'session_id',
        'staff_profile_id',
        'topics_covered',
        'homework_assigned',
        'general_notes',
        'supervisor_private_note',
        'next_session_plan',
        'submitted_at',
        'is_late',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'immutable_datetime',
            'is_late' => 'bool',
        ];
    }

    /** @return HasMany<SessionReportStudent, $this> */
    public function students(): HasMany
    {
        return $this->hasMany(SessionReportStudent::class);
    }

    /**
     * @param Builder<SessionReport> $query
     * @return Builder<SessionReport>
     */
    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->whereNotNull('submitted_at');
    }

    /**
     * @param Builder<SessionReport> $query
     * @return Builder<SessionReport>
     */
    public function scopeForSession(Builder $query, string $sessionId): Builder
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * @param Builder<SessionReport> $query
     * @return Builder<SessionReport>
     */
    public function scopeForStaff(Builder $query, string $staffProfileId): Builder
    {
        return $query->where('staff_profile_id', $staffProfileId);
    }
}
