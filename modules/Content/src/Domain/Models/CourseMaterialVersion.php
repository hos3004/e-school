<?php

declare(strict_types=1);

namespace Modules\Content\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $material_id
 * @property int $revision
 * @property array<string, mixed> $snapshot
 * @property string|null $changed_by
 * @property string $reason
 * @property CarbonImmutable $created_at
 */
final class CourseMaterialVersion extends Model
{
    use HasUlid;

    public $timestamps = false;

    protected $table = 'course_material_versions';

    protected $fillable = [
        'material_id',
        'revision',
        'snapshot',
        'changed_by',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'int',
            'snapshot' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<CourseMaterial, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(CourseMaterial::class, 'material_id');
    }
}
