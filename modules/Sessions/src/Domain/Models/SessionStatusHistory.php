<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $session_id
 * @property string|null $from_status
 * @property string $to_status
 * @property string|null $reason
 * @property string|null $changed_by
 * @property CarbonImmutable $changed_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 */
final class SessionStatusHistory extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public $timestamps = false;

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

    /** @return BelongsTo<Session, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }
}
