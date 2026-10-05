<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $assessment_id
 * @property string $student_profile_id
 * @property string|null $reactivation_request_id
 * @property int $attempt_number
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $submitted_at
 * @property int|null $score
 * @property bool|null $passed
 * @property string|null $graded_by
 * @property CarbonImmutable|null $graded_at
 * @property string|null $feedback
 * @property array<string, mixed> $answers
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Assessment $assessment
 */
final class AssessmentAttempt extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

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
        'feedback',
        'answers',
        'created_at',
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
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id');
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopePassed(Builder $query): Builder
    {
        return $query->where('passed', true);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForStudent(Builder $query, string $studentProfileId): Builder
    {
        return $query->where('student_profile_id', $studentProfileId);
    }
}
