<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $organization_id
 * @property string|null $program_id
 * @property string|null $parent_id
 * @property string $code
 * @property array<string, string> $name
 * @property array<string, string>|null $description
 * @property bool $is_active
 * @property int $sort_order
 * @property int $courses_count
 * @property-read Program|null $program
 * @property-read ProgramCategory|null $parent
 * @property-read Collection<int, ProgramCategory> $children
 * @property-read Collection<int, Course> $courses
 */
final class ProgramCategory extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'program_categories';

    protected $fillable = [
        'organization_id',
        'program_id',
        'parent_id',
        'code',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    /** @return BelongsTo<ProgramCategory, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<ProgramCategory, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return BelongsToMany<Course, $this> */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_category', 'category_id', 'course_id')
            ->withTimestamps();
    }
}
