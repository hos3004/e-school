<?php

declare(strict_types=1);

namespace Modules\Discipline\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Discipline\Domain\Enums\ReactivationStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * طلب إعادة تفعيل تسجيل مجمّد — يمر باختبار جدية قبل العودة إلى Active.
 *
 * حدود المحاولات وفترة التهدئة كلها من config('discipline.reactivation').
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
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /** طلبات تسجيل طالب محدد. */
    public function scopeForEnrollment(Builder $query, string $enrollmentId): Builder
    {
        return $query->where('enrollment_id', $enrollmentId);
    }

    /** الطلبات التي تنتظر قرارًا بعد (غير النهائية). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            ReactivationStatus::Approved->value,
            ReactivationStatus::Rejected->value,
            ReactivationStatus::Cancelled->value,
        ]);
    }

    /** الطلبات التي قدّمها مستخدم محدد — لملكية السجل في السياسة. */
    public function scopeRequestedBy(Builder $query, string $userId): Builder
    {
        return $query->where('requested_by', $userId);
    }

    public function isOpen(): bool
    {
        return ! $this->status->isTerminal();
    }
}
