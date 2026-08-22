<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Academics\Database\Factories\CourseFactory;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
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
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'completion_rules' => 'array',
            'total_sessions' => 'int',
            'is_active' => 'bool',
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
