<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\VirtualClassroom\Domain\Enums\ClassroomEventType;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class ClassroomEvent extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public const UPDATED_AT = null;

    protected $table = 'classroom_events';

    protected $fillable = [
        'classroom_id',
        'idempotency_key',
        'event_type',
        'external_user_id',
        'user_id',
        'occurred_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => ClassroomEventType::class,
            'occurred_at' => 'immutable_datetime',
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeOfType(Builder $query, ClassroomEventType $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForParticipant(Builder $query, string $externalUserId): Builder
    {
        return $query->where('external_user_id', $externalUserId);
    }
}
