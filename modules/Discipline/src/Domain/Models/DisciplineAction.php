<?php

declare(strict_types=1);

namespace Modules\Discipline\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Discipline\Domain\Enums\DisciplineActionType;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * إجراء انضباط مُطبَّق (تنبيه / إنذار / تجميد...) — سجل تاريخي.
 *
 * يُنشأ من محرّك التصعيد عند بلوغ عتبة من config('discipline.ladder')،
 * ويربط الإجراء بالحدث المُطلِق له عبر triggered_by_event_id.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $enrollment_id
 * @property string $triggered_by_event_id
 * @property DisciplineActionType $action
 * @property int $threshold_reached
 * @property string $window_key
 * @property bool $is_automatic
 * @property CarbonImmutable $applied_at
 * @property string|null $applied_by
 * @property string|null $notes
 */
final class DisciplineAction extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'discipline_actions';

    protected $fillable = [
        'organization_id',
        'enrollment_id',
        'triggered_by_event_id',
        'action',
        'threshold_reached',
        'window_key',
        'is_automatic',
        'applied_at',
        'applied_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'action' => DisciplineActionType::class,
            'threshold_reached' => 'integer',
            'is_automatic' => 'boolean',
            'applied_at' => 'immutable_datetime',
        ];
    }

    /** إجراءات مؤسسة واحدة. */
    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /** إجراءات تسجيل طالب محدد. */
    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForEnrollment(Builder $query, string $enrollmentId): Builder
    {
        return $query->where('enrollment_id', $enrollmentId);
    }

    /** الإجراءات الآلية التي طبّقها المحرّك دون تدخل بشري. */
    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeAutomatic(Builder $query): Builder
    {
        return $query->where('is_automatic', true);
    }

    /** إجراءات نافذة احتساب محددة. */
    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeInWindow(Builder $query, string $windowKey): Builder
    {
        return $query->where('window_key', $windowKey);
    }
}
