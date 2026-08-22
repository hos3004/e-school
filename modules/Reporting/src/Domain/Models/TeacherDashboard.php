<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;
use Shared\ValueObjects\Money;

/**
 * لوحة المعلم — Read Model مسطّح لكل ملف موظف.
 *
 * المستحق الصافي قراءة تجميعية من قيود الرواتب (أعداد صحيحة بالوحدات
 * الصغرى) — لا يمس دفتر الأستاذ إطلاقًا.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $staff_profile_id
 * @property int $sessions_total
 * @property int $sessions_completed
 * @property int $cancellations_by_self
 * @property int $postponements
 * @property int $payout_minor
 * @property string $currency
 * @property CarbonImmutable|null $last_session_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
final class TeacherDashboard extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'report_teacher_dashboards';

    protected $fillable = [
        'organization_id',
        'staff_profile_id',
        'sessions_total',
        'sessions_completed',
        'cancellations_by_self',
        'postponements',
        'payout_minor',
        'currency',
        'last_session_at',
    ];

    protected function casts(): array
    {
        return [
            'sessions_total' => 'integer',
            'sessions_completed' => 'integer',
            'cancellations_by_self' => 'integer',
            'postponements' => 'integer',
            'payout_minor' => 'integer',
            'last_session_at' => 'immutable_datetime',
        ];
    }

    public function payout(): Money
    {
        return Money::of((int) $this->payout_minor, (string) $this->currency);
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
}
