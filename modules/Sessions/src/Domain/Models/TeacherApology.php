<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Sessions\Domain\Enums\ApologyStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * اعتذار معلم عن حصة.
 *
 * لا يحمل هذا النموذج أي منطق إلغاء — الاعتذار لا يُلغي الحصة إطلاقًا
 * (docs/client-answers.md §ي). أقصى أثره أنه يفتح البحث عن بديل.
 */
final class TeacherApology extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'teacher_apologies';

    protected $fillable = [
        'organization_id',
        'session_id',
        'staff_profile_id',
        'status',
        'reason',
        'submitted_at',
        'is_late_notice',
        'notice_minutes',
        'decided_by',
        'decided_at',
        'decision_reason',
        'substitution_id',
        'occurrence_in_window',
        'window_days',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApologyStatus::class,
            'submitted_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
            'is_late_notice' => 'boolean',
            'notice_minutes' => 'integer',
            'occurrence_in_window' => 'integer',
            'window_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Session, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForStaff(Builder $query, string $staffProfileId): Builder
    {
        return $query->where('staff_profile_id', $staffProfileId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ApologyStatus::Submitted);
    }

    /**
     * اعتذارات معتمدة لم يُسند لها بديل بعد — هذه هي التي تُصعَّد للإدارة
     * قبل موعد الحصة، ولا تعني إطلاقًا أن الحصة ستُلغى.
     */
    public function scopeAwaitingSubstitute(Builder $query): Builder
    {
        return $query->where('status', ApologyStatus::Approved);
    }
}
