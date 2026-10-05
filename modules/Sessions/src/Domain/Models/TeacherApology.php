<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Sessions\Domain\Enums\ApologyStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $session_id
 * @property string $staff_profile_id
 * @property ApologyStatus $status
 * @property string $reason
 * @property CarbonImmutable $submitted_at
 * @property bool $is_late_notice
 * @property int|null $notice_minutes
 * @property string|null $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property string|null $decision_reason
 * @property string|null $substitution_id
 * @property int|null $occurrence_in_window
 * @property int|null $window_days
 * @property CarbonImmutable|null $substitute_search_started_at
 * @property CarbonImmutable|null $last_substitute_search_at
 * @property list<string>|null $substitute_candidate_ids
 * @property int $substitute_candidate_count
 *
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
        'substitute_search_started_at',
        'last_substitute_search_at',
        'substitute_candidate_ids',
        'substitute_candidate_count',
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
            'substitute_search_started_at' => 'immutable_datetime',
            'last_substitute_search_at' => 'immutable_datetime',
            'substitute_candidate_ids' => 'array',
            'substitute_candidate_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Session, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
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
    public function scopeForStaff(Builder $query, string $staffProfileId): Builder
    {
        return $query->where('staff_profile_id', $staffProfileId);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ApologyStatus::Submitted);
    }

    /**
     * اعتذارات معتمدة لم يُسند لها بديل بعد — هذه هي التي تُصعَّد للإدارة
     * قبل موعد الحصة، ولا تعني إطلاقًا أن الحصة ستُلغى.
     */
    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeAwaitingSubstitute(Builder $query): Builder
    {
        return $query->where('status', ApologyStatus::Approved);
    }
}
