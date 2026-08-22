<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class SessionParticipant extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public const UPDATED_AT = null;

    protected $table = 'session_participants';

    protected $fillable = [
        'session_id',
        'student_profile_id',
        'enrollment_id',
        'join_url_token',
        'invited_at',
        'first_joined_at',
        'last_left_at',
        'attended_minutes',
    ];

    protected function casts(): array
    {
        return [
            'invited_at' => 'immutable_datetime',
            'first_joined_at' => 'immutable_datetime',
            'last_left_at' => 'immutable_datetime',
            'attended_minutes' => 'int',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForStudent(Builder $query, string $studentProfileId): Builder
    {
        return $query->where('student_profile_id', $studentProfileId);
    }
}
