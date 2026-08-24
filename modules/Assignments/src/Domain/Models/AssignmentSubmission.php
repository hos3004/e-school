<?php

declare(strict_types=1);

namespace Modules\Assignments\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Assignments\Domain\Enums\AssignmentSubmissionStatus;
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

    /**
     * `attachments` عمود NOT NULL بلا default في قاعدة البيانات، وكل مسارات
     * إنشاء التسليم تبدأ بصف فارغ قبل أن يرفع الطالب شيئًا. القيمة هنا تجعل
     * الصف الجديد صالحًا أيًا كان المسار — متحكم API أو بوابة أو factory —
     * بدل تكرار المفتاح في كل موضع إنشاء.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'attachments' => '[]',
    ];

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
            'status' => AssignmentSubmissionStatus::class,
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
        $value = $status instanceof AssignmentSubmissionStatus ? $status->value : $status;

        return $query->where('status', $value);
    }

    public function scopeGraded(Builder $query): Builder
    {
        return $query->ofStatus(AssignmentSubmissionStatus::Graded);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->ofStatus(AssignmentSubmissionStatus::Pending);
    }
}
