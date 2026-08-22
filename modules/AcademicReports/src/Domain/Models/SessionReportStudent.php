<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * تقييم الطالب داخل تقرير الحصة: مشاركة وأداء والتزام من ١ إلى ٥.
 *
 * student_profile_id عمود عادي — نموذجه مملوك لموديول Students.
 */
final class SessionReportStudent extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public $timestamps = false;

    /** أدنى تقييم مسموح لكل محور. */
    public const MIN_SCORE = 1;

    /** أقصى تقييم مسموح لكل محور. */
    public const MAX_SCORE = 5;

    protected $table = 'session_report_students';

    protected $fillable = [
        'session_report_id',
        'student_profile_id',
        'participation',
        'performance',
        'commitment',
        'strengths',
        'weaknesses',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'participation' => 'int',
            'performance' => 'int',
            'commitment' => 'int',
        ];
    }

    public function sessionReport(): BelongsTo
    {
        return $this->belongsTo(SessionReport::class);
    }

    public function scopeForStudent(Builder $query, string $studentProfileId): Builder
    {
        return $query->where('student_profile_id', $studentProfileId);
    }

    public function scopeForReport(Builder $query, string $sessionReportId): Builder
    {
        return $query->where('session_report_id', $sessionReportId);
    }
}
