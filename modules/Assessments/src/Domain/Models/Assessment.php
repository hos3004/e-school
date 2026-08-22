<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Assessments\Domain\Enums\AssessmentType;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property string|null $course_id
 * @property AssessmentType $type
 * @property array<string, mixed> $title
 * @property array<string, mixed> $instructions
 * @property int $total_score
 * @property int $passing_score
 * @property int|null $duration_minutes
 * @property int $max_attempts
 * @property CarbonImmutable $available_from
 * @property CarbonImmutable $available_to
 * @property string $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Question> $questions
 * @property-read Collection<int, AssessmentAttempt> $attempts
 */
final class Assessment extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'assessments';

    protected $fillable = [
        'organization_id',
        'course_id',
        'type',
        'title',
        'instructions',
        'total_score',
        'passing_score',
        'duration_minutes',
        'max_attempts',
        'available_from',
        'available_to',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => AssessmentType::class,
            'title' => 'array',
            'instructions' => 'array',
            'total_score' => 'int',
            'passing_score' => 'int',
            'duration_minutes' => 'int',
            'max_attempts' => 'int',
            'available_from' => 'immutable_datetime',
            'available_to' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /** @return HasMany<AssessmentAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(AssessmentAttempt::class);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
