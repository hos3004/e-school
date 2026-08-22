<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class SessionReportStudent extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public $timestamps = false;

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
}
