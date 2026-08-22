<?php

declare(strict_types=1);

namespace Modules\Discipline\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Discipline\Domain\Enums\ViolationType;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * حدث مخالفة واحد — سجل غير قابل للحذف.
 *
 * العدّاد الشهري للطالب لا يُخزَّن؛ يُحسب جمع أحداث هذه الجدول داخل
 * نافذة واحدة (window_key) مع استبعاد غير القابلة للعدّ والعفو عنها.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $enrollment_id
 * @property string $student_profile_id
 * @property string|null $session_id
 * @property ViolationType $type
 * @property CarbonImmutable $occurred_at
 * @property string $window_key
 * @property bool $is_countable
 * @property string|null $waived_by
 * @property CarbonImmutable|null $waived_at
 * @property string|null $waiver_reason
 */
final class ViolationEvent extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'violation_events';

    protected $fillable = [
        'organization_id',
        'enrollment_id',
        'student_profile_id',
        'session_id',
        'type',
        'occurred_at',
        'window_key',
        'is_countable',
        'waived_by',
        'waived_at',
        'waiver_reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => ViolationType::class,
            'occurred_at' => 'immutable_datetime',
            'is_countable' => 'boolean',
            'waived_at' => 'immutable_datetime',
        ];
    }

    /** أحداث مؤسسة واحدة. */
    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /** أحداث تسجيل طالب محدد. */
    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForEnrollment(Builder $query, string $enrollmentId): Builder
    {
        return $query->where('enrollment_id', $enrollmentId);
    }

    /** أحداث نافذة احتساب محددة. */
    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeInWindow(Builder $query, string $windowKey): Builder
    {
        return $query->where('window_key', $windowKey);
    }

    /**
     * الأحداث المؤهلة للعدّ — مطابقة لشرط الفهرس الجزئي في الهجرة:
     * is_countable AND waived_at IS NULL.
     */
    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeCountable(Builder $query): Builder
    {
        return $query->where('is_countable', true)->whereNull('waived_at');
    }

    /** مخالفات لم يُعفَ عنها بعد. */
    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeNotWaived(Builder $query): Builder
    {
        return $query->whereNull('waived_at');
    }

    public function isWaived(): bool
    {
        return $this->waived_at !== null;
    }
}
