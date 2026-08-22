<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * تقويم أكاديمي — عام درسي له بداية ونهاية.
 *
 * مؤسسة واحدة لديها تقويم نشط واحد على الأكثر (حدّه في
 * config('organization.rules.max_active_calendars'))؛ الجدولة كلها
 * ترتبط بالتقويم النشط.
 *
 * @property string $id
 * @property string $organization_id
 * @property array<string, string> $name
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $organization
 * @property-read Collection<int, Holiday> $holidays
 */
final class AcademicCalendar extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'academic_calendars';

    /** @var list<string> */
    protected $fillable = [
        'organization_id',
        'name',
        'starts_on',
        'ends_on',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'starts_on' => 'immutable_datetime',
            'ends_on' => 'immutable_datetime',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<Holiday, $this> */
    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }

    /**
     * التقويمات النشطة فقط.
     */
    /**
     * @param Builder<AcademicCalendar> $query
     * @return Builder<AcademicCalendar>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * تقويمات مؤسسة واحدة.
     */
    /**
     * @param Builder<AcademicCalendar> $query
     * @return Builder<AcademicCalendar>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * هل يتقاطع نطاق هذا التقويم مع نطاق معطى (بغضّ الطرفين)؟
     */
    public function overlapsWith(string|CarbonImmutable $startsOn, string|CarbonImmutable $endsOn): bool
    {
        $start = $startsOn instanceof CarbonImmutable ? $startsOn : CarbonImmutable::parse($startsOn);
        $end = $endsOn instanceof CarbonImmutable ? $endsOn : CarbonImmutable::parse($endsOn);

        return $this->starts_on->lte($end) && $this->ends_on->gte($start);
    }
}
