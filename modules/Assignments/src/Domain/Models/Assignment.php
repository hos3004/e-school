<?php

declare(strict_types=1);

namespace Modules\Assignments\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Assignments\Domain\Enums\AssignmentOperationalStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * نشاط (واجب) يُسند لطلاب مقرر أو مجموعة، وله موعد تسليم ودرجة عليا.
 *
 * المعرّفات الخارجية (course_id, group_id, staff_profile_id) أعمدة عادية —
 * لا علاقات Eloquent عبر حدود الموديولات.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $course_id
 * @property string|null $group_id
 * @property string $staff_profile_id
 * @property array<string, string> $title
 * @property array<string, string> $instructions
 * @property list<string> $attachments
 * @property CarbonImmutable $assigned_at
 * @property CarbonImmutable $due_at
 * @property int $max_score
 * @property bool $allows_late
 * @property int $late_penalty_percent
 * @property CarbonImmutable|null $deleted_at
 */
final class Assignment extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'assignments';

    protected $fillable = [
        'id',
        'organization_id',
        'course_id',
        'group_id',
        'staff_profile_id',
        'title',
        'instructions',
        'attachments',
        'assigned_at',
        'due_at',
        'max_score',
        'allows_late',
        'late_penalty_percent',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'instructions' => 'array',
            'attachments' => 'array',
            'assigned_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
            'max_score' => 'int',
            'allows_late' => 'bool',
            'late_penalty_percent' => 'int',
        ];
    }

    /** @return HasMany<AssignmentSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForCourse(Builder $query, string $courseId): Builder
    {
        return $query->where('course_id', $courseId);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('due_at', '>=', now());
    }

    public function isPastDue(): bool
    {
        return $this->due_at->isBefore(now());
    }

    public function operationalStatus(): AssignmentOperationalStatus
    {
        if ($this->assigned_at->isAfter(now())) {
            return AssignmentOperationalStatus::Scheduled;
        }

        if (!$this->isPastDue()) {
            return AssignmentOperationalStatus::Open;
        }

        return $this->allows_late
            ? AssignmentOperationalStatus::LateWindow
            : AssignmentOperationalStatus::Closed;
    }
}
