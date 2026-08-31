<?php

declare(strict_types=1);

namespace Modules\Assignments\Domain\Models;

use Carbon\CarbonImmutable;
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
 *
 * @property string $id
 * @property string $assignment_id
 * @property string $student_profile_id
 * @property CarbonImmutable|null $submitted_at
 * @property bool $is_late
 * @property string|null $content
 * @property list<string> $attachments
 * @property int|null $raw_score
 * @property int $penalty_points
 * @property int|null $score
 * @property string|null $feedback
 * @property string|null $graded_by
 * @property CarbonImmutable|null $graded_at
 * @property AssignmentSubmissionStatus $status
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
        'raw_score',
        'penalty_points',
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
            'raw_score' => 'int',
            'penalty_points' => 'int',
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

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->whereHas('assignment', fn (Builder $q): Builder => $q->where('organization_id', $organizationId));
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeOfStatus(Builder $query, mixed $status): Builder
    {
        $value = $status instanceof AssignmentSubmissionStatus ? $status->value : $status;

        return $query->where('status', $value);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeGraded(Builder $query): Builder
    {
        return $query->where('status', AssignmentSubmissionStatus::Graded->value);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', AssignmentSubmissionStatus::Pending->value);
    }
}
