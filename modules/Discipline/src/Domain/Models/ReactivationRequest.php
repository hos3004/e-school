<?php

declare(strict_types=1);

namespace Modules\Discipline\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Discipline\Domain\Enums\ReactivationStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * طلب إعادة تفعيل تسجيل مجمّد — يمر باختبار جدية قبل العودة إلى Active.
 *
 * حدود المحاولات وفترة التهدئة كلها من config('discipline.reactivation').
 *
 * @property string $id
 * @property string $organization_id
 * @property string $enrollment_id
 * @property string $requested_by
 * @property ReactivationStatus $status
 * @property int $attempt_number
 * @property string|null $assessment_attempt_id
 * @property string $student_statement
 * @property string|null $reviewer_id
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $decision_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class ReactivationRequest extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'reactivation_requests';

    protected $fillable = [
        'organization_id',
        'enrollment_id',
        'requested_by',
        'status',
        'attempt_number',
        'assessment_attempt_id',
        'student_statement',
        'reviewer_id',
        'reviewed_at',
        'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReactivationStatus::class,
            'attempt_number' => 'integer',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    /** طلبات مؤسسة واحدة. */
    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /** طلبات تسجيل طالب محدد. */
    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForEnrollment(Builder $query, string $enrollmentId): Builder
    {
        return $query->where('enrollment_id', $enrollmentId);
    }

    /** الطلبات التي تنتظر قرارًا بعد (غير النهائية). */
    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            ReactivationStatus::Approved->value,
            ReactivationStatus::Rejected->value,
            ReactivationStatus::Cancelled->value,
        ]);
    }

    /** الطلبات التي قدّمها مستخدم محدد — لملكية السجل في السياسة. */
    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeRequestedBy(Builder $query, string $userId): Builder
    {
        return $query->where('requested_by', $userId);
    }

    public function isOpen(): bool
    {
        return !$this->status->isTerminal();
    }
}
