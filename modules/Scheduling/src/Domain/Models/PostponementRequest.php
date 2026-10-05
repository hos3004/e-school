<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Scheduling\Domain\Enums\PostponementStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $session_id
 * @property string $requested_by
 * @property string|null $requested_for_student_id
 * @property PostponementStatus $status
 * @property bool $requires_admin_review
 * @property CarbonImmutable $proposed_start
 * @property CarbonImmutable|null $proposed_by_teacher_start
 * @property CarbonImmutable|null $agreed_start
 * @property string|null $makeup_session_id
 * @property string $reason
 * @property string|null $teacher_note
 * @property string|null $admin_note
 * @property string|null $responded_by
 * @property CarbonImmutable|null $responded_at
 * @property CarbonImmutable $expires_at
 */
final class PostponementRequest extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'postponement_requests';

    protected $fillable = [
        'organization_id',
        'session_id',
        'requested_by',
        'requested_for_student_id',
        'status',
        'requires_admin_review',
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

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    protected function casts(): array
    {
        return [
            'status' => PostponementStatus::class,
            'requires_admin_review' => 'bool',
            'proposed_start' => 'immutable_datetime',
            'proposed_by_teacher_start' => 'immutable_datetime',
            'agreed_start' => 'immutable_datetime',
            'responded_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [
            PostponementStatus::Requested->value,
            PostponementStatus::AlternativeProposed->value,
        ]);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }
}
