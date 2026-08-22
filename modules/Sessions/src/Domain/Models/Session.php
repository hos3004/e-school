<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property string|null $schedule_id
 * @property string $group_id
 * @property string $course_id
 * @property string $staff_profile_id
 * @property string|null $substitute_for_staff_id
 * @property string|null $makeup_for_session_id
 * @property string $session_type
 * @property SessionStatus $status
 * @property CarbonImmutable $scheduled_start
 * @property CarbonImmutable $scheduled_end
 * @property CarbonImmutable|null $actual_start
 * @property CarbonImmutable|null $actual_end
 * @property array<string, mixed> $title
 * @property string|null $notes
 * @property string|null $cancelled_by
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property CarbonImmutable|null $finalized_at
 * @property string|null $finalized_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, SessionParticipant> $participants
 * @property-read Collection<int, SessionStatusHistory> $statusHistory
 */
final class Session extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'sessions';

    protected $fillable = [
        'organization_id',
        'schedule_id',
        'group_id',
        'course_id',
        'staff_profile_id',
        'original_teacher_id',
        'substitute_for_staff_id',
        'makeup_for_session_id',
        'session_type',
        'status',
        'scheduled_start',
        'scheduled_end',
        'actual_start',
        'actual_end',
        'title',
        'notes',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'finalized_at',
        'finalized_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SessionStatus::class,
            'scheduled_start' => 'immutable_datetime',
            'scheduled_end' => 'immutable_datetime',
            'actual_start' => 'immutable_datetime',
            'actual_end' => 'immutable_datetime',
            'title' => 'array',
            'cancelled_at' => 'immutable_datetime',
            'finalized_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        /*
         * المعلم الأصلي يُثبَّت لحظة الإنشاء تلقائيًا.
         *
         * نفرضه هنا لا في كل Action لأن القاعدة ملك النموذج: أي مسار ينشئ حصة
         * — جدولة، حصة تلافي، بذرة، اختبار — يجب أن يخرج بحصة تعرف معلمها
         * الأصلي. الاعتماد على تذكّر كل مُستدعٍ كان سيترك ثغرة صامتة.
         */
        static::creating(function (self $session): void {
            if ($session->original_teacher_id === null) {
                $session->original_teacher_id = $session->staff_profile_id;
            }
        });

        /*
         * ولا يتغيّر بعدها أبدًا (client-answers §كط: «لا تحدث original»).
         * الاستبدال يغيّر staff_profile_id — المعلم الفعلي — لا هذا العمود.
         */
        static::updating(function (self $session): void {
            if ($session->isDirty('original_teacher_id')) {
                $session->original_teacher_id = $session->getOriginal('original_teacher_id');
            }
        });
    }

    /**
     * المعلم الذي ينفّذ الحصة فعلًا — هو staff_profile_id.
     *
     * نكشفه بهذا الاسم لأن عقد العميل يتحدث عن «actual teacher»، وترك المعنى
     * ضمنيًا في اسم عمود عام كان سيربك كل من يقرأ الكود لاحقًا.
     */
    public function actualTeacherId(): ?string
    {
        return $this->staff_profile_id === null ? null : (string) $this->staff_profile_id;
    }

    public function isCoveredBySubstitute(): bool
    {
        return $this->original_teacher_id !== null
            && (string) $this->original_teacher_id !== (string) $this->staff_profile_id;
    }

    /**
     * @return HasMany<SessionParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(SessionParticipant::class);
    }

    /**
     * @return HasMany<SessionStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(SessionStatusHistory::class);
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
    public function scopeWithStatus(Builder $query, SessionStatus $status): Builder
    {
        return $query->where('status', $status);
    }
}
