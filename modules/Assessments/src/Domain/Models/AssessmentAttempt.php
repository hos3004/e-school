<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class AssessmentAttempt extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'assessment_attempts';

    protected $fillable = [
        'assessment_id',
        'student_profile_id',
        'reactivation_request_id',
        'attempt_number',
        'started_at',
        'submitted_at',
        'score',
        'passed',
        'graded_by',
        'graded_at',
        'answers',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'int',
            'started_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'score' => 'int',
            'passed' => 'bool',
            'graded_at' => 'immutable_datetime',
            'answers' => 'array',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id');
    }

    public function scopePassed(Builder $query): Builder
    {
        return $query->where('passed', true);
    }

    public function scopeForStudent(Builder $query, string $studentProfileId): Builder
    {
        return $query->where('student_profile_id', $studentProfileId);
    }
}
