<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class SessionStatusHistory extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public const UPDATED_AT = null;

    protected $table = 'session_status_history';

    protected $fillable = [
        'session_id',
        'from_status',
        'to_status',
        'reason',
        'changed_by',
        'changed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }
}
