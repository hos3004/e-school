<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Assessments\Domain\Enums\AssessmentType;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

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

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(AssessmentAttempt::class);
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
