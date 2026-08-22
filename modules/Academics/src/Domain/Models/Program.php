<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Academics\Database\Factories\ProgramFactory;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class Program extends Model
{
    /** @use HasFactory<ProgramFactory> */
    use HasModuleFactory;

    use HasUlid;
    use SoftDeletes;

    protected $table = 'programs';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'description',
        'duration_weeks',
        'default_session_minutes',
        'default_rate',
        'currency',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'duration_weeks' => 'int',
            'default_session_minutes' => 'int',
            'default_rate' => 'int',
            'is_active' => 'bool',
            'sort_order' => 'int',
        ];
    }

    protected static function newFactory(): ProgramFactory
    {
        return ProgramFactory::new();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * @return HasMany<Level, $this>
     */
    public function levels(): HasMany
    {
        return $this->hasMany(Level::class);
    }
}
