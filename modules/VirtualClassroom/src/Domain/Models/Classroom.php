<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\VirtualClassroom\Domain\Enums\ClassroomEventType;
use Modules\VirtualClassroom\Domain\Enums\ClassroomHealthStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $ended_at
 */
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
            'health_status' => ClassroomHealthStatus::class,
            'external_meta' => 'array',
            'moderator_secret' => 'encrypted',
            'attendee_secret' => 'encrypted',
            'created_remote_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'max_concurrent_participants' => 'int',
        ];
    }

    /**
     * @return HasMany<ClassroomEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ClassroomEvent::class);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForSession(Builder $query, string $sessionId): Builder
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * فصل مفتوح الآن: بدأ عند المزوّد ولم يُنهَ بعد.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotNull('started_at')->whereNull('ended_at');
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeWithHealth(Builder $query, ClassroomHealthStatus $status): Builder
    {
        return $query->where('health_status', $status);
    }

    /** هل بدأ الفصل فعليًا عند المزوّد؟ */
    public function hasStarted(): bool
    {
        return $this->started_at !== null;
    }

    /** هل انتهى الفصل؟ */
    public function hasEnded(): bool
    {
        return $this->ended_at !== null;
    }

    /** هل الفصل جارٍ الآن (بدأ ولم ينته)؟ */
    public function isRunning(): bool
    {
        return $this->hasStarted() && !$this->hasEnded();
    }

    /** هل سُجِّل حدث من نوع معيّن لهذا الفصل؟ */
    public function hasEventOfType(ClassroomEventType $type): bool
    {
        return $this->events()->ofType($type)->exists();
    }
}
