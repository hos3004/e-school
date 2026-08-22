<?php

declare(strict_types=1);

namespace Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class CourseMaterial extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    public const UPDATED_AT = null;

    protected $table = 'course_materials';

    protected $fillable = [
        'course_id',
        'title',
        'type',
        'disk',
        'path',
        'external_url',
        'size_bytes',
        'visible_from',
        'visible_to',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'size_bytes' => 'int',
            'visible_from' => 'immutable_datetime',
            'visible_to' => 'immutable_datetime',
        ];
    }
}
