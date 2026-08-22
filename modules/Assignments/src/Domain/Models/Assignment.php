<?php

declare(strict_types=1);

namespace Modules\Assignments\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * نشاط (واجب) يُسند لطلاب مقرر أو مجموعة، وله موعد تسليم ودرجة عليا.
 *
 * المعرّفات الخارجية (course_id, group_id, staff_profile_id) أعمدة عادية —
 * لا علاقات Eloquent عبر حدود الموديولات.
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

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForCourse(Builder $query, string $courseId): Builder
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('due_at', '>=', now());
    }

    public function isPastDue(): bool
    {
        return $this->due_at->isBefore(now());
    }
}
