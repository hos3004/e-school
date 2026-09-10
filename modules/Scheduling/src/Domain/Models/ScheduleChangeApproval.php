<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Scheduling\Domain\Enums\ScheduleChangeApprovalStatus;
use Shared\Concerns\HasUlid;

/**
 * رد طالب واحد على طلب تغيير الموعد الدائم؛ الطلب لا يُطبَّق إلا باكتمال القبول.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $schedule_change_request_id
 * @property string $student_profile_id
 * @property string|null $student_user_id
 * @property ScheduleChangeApprovalStatus $status
 * @property string|null $responded_by
 * @property CarbonImmutable|null $responded_at
 * @property string|null $note
 */
final class ScheduleChangeApproval extends Model
{
    use HasUlid;

    protected $table = 'schedule_change_approvals';

    protected $fillable = [
        'organization_id',
        'schedule_change_request_id',
        'student_profile_id',
        'student_user_id',
        'status',
        'responded_by',
        'responded_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => ScheduleChangeApprovalStatus::class,
            'responded_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ScheduleChangeRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ScheduleChangeRequest::class, 'schedule_change_request_id');
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
