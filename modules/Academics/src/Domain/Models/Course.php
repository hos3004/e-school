<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Academics\Database\Factories\CourseFactory;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Enums\TargetGender;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $level_id
 * @property string $code
 * @property array<string, string> $name
 * @property array<string, string>|null $description
 * @property int|null $total_sessions
 * @property array<string, mixed>|null $completion_rules
 * @property bool $is_active
 * @property SessionMode $session_mode
 * @property TargetGender|null $target_gender
 * @property int|null $age_from
 * @property int|null $age_to
 * @property int|null $default_duration_minutes
 * @property int|null $sessions_per_week
 * @property array<string, mixed>|null $prerequisites
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 * @property-read Level|null $level
 * @property-read Collection<int, ProgramCategory> $categories
 */
final class Course extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'courses';

    protected $fillable = [
        'organization_id',
        'level_id',
        'code',
        'name',
        'description',
        'total_sessions',
        'completion_rules',
        'is_active',
        'session_mode',
        'age_from',
        'age_to',
        'target_gender',
        'default_duration_minutes',
        'sessions_per_week',
        'prerequisites',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'completion_rules' => 'array',
            'prerequisites' => 'array',
            'total_sessions' => 'int',
            'is_active' => 'bool',
            'session_mode' => SessionMode::class,
            'target_gender' => TargetGender::class,
            'age_from' => 'int',
            'age_to' => 'int',
            'default_duration_minutes' => 'int',
            'sessions_per_week' => 'int',
        ];
    }

    protected static function newFactory(): CourseFactory
    {
        return CourseFactory::new();
    }

    /**
     * @return BelongsTo<Level, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /** @return BelongsToMany<ProgramCategory, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProgramCategory::class, 'course_category', 'course_id', 'category_id')
            ->withTimestamps();
    }

    /**
     * @param Builder<Course> $query
     * @return Builder<Course>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param Builder<Course> $query
     * @return Builder<Course>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
