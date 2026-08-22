<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Modules\Scheduling\Domain\Enums\PostponementStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class PostponementRequest extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'postponement_requests';

    protected $fillable = [
        'session_id',
        'requested_by',
        'requested_for_student_id',
        'status',
        'proposed_start',
        'proposed_by_teacher_start',
        'agreed_start',
        'makeup_session_id',
        'reason',
        'teacher_note',
        'admin_note',
        'responded_by',
        'responded_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PostponementStatus::class,
            'proposed_start' => 'immutable_datetime',
            'proposed_by_teacher_start' => 'immutable_datetime',
            'agreed_start' => 'immutable_datetime',
            'responded_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [
            PostponementStatus::Requested->value,
            PostponementStatus::AlternativeProposed->value,
        ]);
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }
}
