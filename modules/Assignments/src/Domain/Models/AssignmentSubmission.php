<?php

declare(strict_types=1);

namespace Modules\Assignments\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * تسليم الطالب لنشاط — صف واحد لكل (نشاط، طالب).
 *
 * الحالة دورة حياة عبر AssignmentSubmissionStatus، والانتقال دائمًا
 * عبر canTransitionTo داخل إجراءات التطبيق لا مباشرة.
 */
final class AssignmentSubmission extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'assignment_submissions';

    protected $fillable = [
        'id',
        'assignment_id',
        'student_profile_id',
        'submitted_at',
        'is_late',
        'content',
        'attachments',
        'score',
        'feedback',
        'graded_by',
        'graded_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'immutable_datetime',
            'is_late' => 'bool',
            'attachments' => 'array',
            'score' => 'int',
            'graded_at' => 'immutable_datetime',
            'status' => Enums\AssignmentSubmissionStatus::class,
        ];
    }

    /** @return BelongsTo<Assignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->whereHas('assignment', fn (Builder $q): Builder => $q->where('organization_id', $organizationId));
    }

    public function scopeOfStatus(Builder $query, mixed $status): Builder
    {
        $value = $status instanceof Enums\AssignmentSubmissionStatus ? $status->value : $status;

        return $query->where('status', $value);
    }

    public function scopeGraded(Builder $query): Builder
    {
        return $query->ofStatus(Enums\AssignmentSubmissionStatus::Graded);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->ofStatus(Enums\AssignmentSubmissionStatus::Pending);
    }
}
