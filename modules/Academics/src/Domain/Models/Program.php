<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Academics\Database\Factories\ProgramFactory;
use Modules\Academics\Domain\Enums\ProgramType;
use Modules\Academics\Domain\Enums\TargetGender;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $code
 * @property array<string, string> $name
 * @property array<string, string>|null $description
 * @property int|null $duration_weeks
 * @property int $default_session_minutes
 * @property int|null $default_rate
 * @property string $currency
 * @property bool $is_active
 * @property int $sort_order
 * @property ProgramType $program_type
 * @property CarbonInterface|null $start_date
 * @property CarbonInterface|null $end_date
 * @property TargetGender $target_gender
 * @property int|null $age_from
 * @property int|null $age_to
 * @property array<string, mixed>|null $objectives
 * @property string|null $language
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 * @property-read Collection<int, Level> $levels
 * @property-read ProgramEligibility|null $eligibility
 */
final class Program extends Model
{
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
        'program_type',
        'start_date',
        'end_date',
        'target_gender',
        'age_from',
        'age_to',
        'objectives',
        'language',
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
            'program_type' => ProgramType::class,
            'target_gender' => TargetGender::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'age_from' => 'int',
            'age_to' => 'int',
            'objectives' => 'array',
        ];
    }

    protected static function newFactory(): ProgramFactory
    {
        return ProgramFactory::new();
    }

    /**
     * @param Builder<Program> $query
     * @return Builder<Program>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param Builder<Program> $query
     * @return Builder<Program>
     */
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

    /** @return HasOne<ProgramEligibility, $this> */
    public function eligibility(): HasOne
    {
        return $this->hasOne(ProgramEligibility::class, 'program_id');
    }
}
