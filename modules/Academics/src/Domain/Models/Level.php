<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academics\Database\Factories\LevelFactory;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $program_id
 * @property string $code
 * @property array<string, string> $name
 * @property int $sort_order
 * @property CarbonImmutable|null $closed_at
 * @property string|null $closed_by
 * @property string|null $closure_reason
 * @property array<string, mixed>|null $closure_summary
 * @property CarbonInterface|null $created_at
 * @property-read Program|null $program
 * @property-read Collection<int, Course> $courses
 */
final class Level extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public const UPDATED_AT = null;

    protected $table = 'levels';

    protected $fillable = [
        'program_id',
        'code',
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'immutable_datetime',
            'closure_summary' => 'array',
            'name' => 'array',
            'sort_order' => 'int',
        ];
    }

    protected static function newFactory(): LevelFactory
    {
        return LevelFactory::new();
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return HasMany<Course, $this> */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /**
     * المفتوح — ما يظهر في الواجهة اليومية.
     *
     * الفلترة صريحة في الاستعلام لا عبر global scope: الحصيلة والتقارير تحتاج
     * المُقفل، ولو أُخفي عالميًا لعادت أرقامها أصفارًا من حيث لا يشعر المستدعي.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    /**
     * المُقفل — محتوى الأرشيف.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereNotNull('closed_at');
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }
}
