<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $session_id
 * @property string $student_profile_id
 * @property string $enrollment_id
 * @property string|null $join_url_token
 * @property CarbonImmutable|null $invited_at
 * @property CarbonImmutable|null $first_joined_at
 * @property CarbonImmutable|null $last_left_at
 * @property int $attended_minutes
 * @property Carbon|null $created_at
 * @property-read Session $session
 */
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

    /** @return BelongsTo<Session, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForStudent(Builder $query, string $studentProfileId): Builder
    {
        return $query->where('student_profile_id', $studentProfileId);
    }
}
