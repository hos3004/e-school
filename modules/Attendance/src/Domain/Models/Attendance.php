<?php

declare(strict_types=1);

namespace Modules\Attendance\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * قيد حضور طالب في حصة — واحد لكل مشاركة (session_participant_id فريد).
 *
 * الحالة المشتقة derive_status تُحسب آليًا من دقائق الدخول والخروج،
 * والحالة النهائية status يعتمدها المعلم أو تجاوزها بإدارة موثّق بسبب.
 */
final class Attendance extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'attendances';

    protected $fillable = [
        'session_participant_id',
        'status',
        'derived_status',
        'attended_minutes',
        'joined_after_minutes',
        'left_before_minutes',
        'confirmed_by',
        'confirmed_at',
        'override_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'derived_status' => AttendanceStatus::class,
            'attended_minutes' => 'int',
            'joined_after_minutes' => 'int',
            'left_before_minutes' => 'int',
            'confirmed_at' => 'immutable_datetime',
        ];
    }

    /** هل اعتُمد هذا الحضور (منهائيًا)؟ */
    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    /** هل غيّرت الإدارة الحالة عن حالة الاستنباط؟ */
    public function isOverridden(): bool
    {
        return $this->status !== $this->derived_status;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->whereNotNull('confirmed_at');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUnconfirmed(Builder $query): Builder
    {
        return $query->whereNull('confirmed_at');
    }

    /**
     * @param  Builder<self>  $query
     * @param  list<string>  $participantIds
     * @return Builder<self>
     */
    public function scopeForParticipants(Builder $query, array $participantIds): Builder
    {
        return $query->whereIn('session_participant_id', $participantIds);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithStatus(Builder $query, AttendanceStatus $status): Builder
    {
        return $query->where('status', $status);
    }
}
