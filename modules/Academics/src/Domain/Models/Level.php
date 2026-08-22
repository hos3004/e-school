<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academics\Database\Factories\LevelFactory;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class Level extends Model
{
    /** @use HasFactory<LevelFactory> */
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
}
