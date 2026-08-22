<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class Classroom extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'classrooms';

    protected $fillable = [
        'session_id',
        'provider',
        'external_id',
        'external_meta',
        'moderator_secret',
        'attendee_secret',
        'created_remote_at',
        'started_at',
        'ended_at',
        'max_concurrent_participants',
        'health_status',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'external_meta' => 'array',
            'created_remote_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'max_concurrent_participants' => 'int',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeHealthy(Builder $query): Builder
    {
        return $query->where('health_status', 'healthy');
    }
}
