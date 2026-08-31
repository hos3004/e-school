<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Models;

use Carbon\CarbonInterface;
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
}
