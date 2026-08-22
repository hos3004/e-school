<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Organization\Domain\Enums\HolidaySource;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * عطلة — نطاق أيام يتوقف عندها الجدولة إذا كانت blocks_scheduling.
 *
 * قد ترتبط بتقويم أكاديمي محدد أو تكون على مستوى المؤسسة كلها
 * (academic_calendar_id = null).
 *
 * @property string $id
 * @property string $organization_id
 * @property string|null $academic_calendar_id
 * @property array<string, string> $name
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property HolidaySource $source
 * @property bool $blocks_scheduling
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $organization
 * @property-read AcademicCalendar|null $academicCalendar
 */
final class Holiday extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'holidays';

    /** @var list<string> */
    protected $fillable = [
        'organization_id',
        'academic_calendar_id',
        'name',
        'starts_on',
        'ends_on',
        'source',
        'blocks_scheduling',
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
            'source' => HolidaySource::class,
            'blocks_scheduling' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<AcademicCalendar, $this> */
    public function academicCalendar(): BelongsTo
    {
        return $this->belongsTo(AcademicCalendar::class);
    }

    /**
     * عطل مؤسسة واحدة.
     */
    /**
     * @param Builder<Holiday> $query
     * @return Builder<Holiday>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * العطل التي تعيق الجدولة فقط.
     */
    /**
     * @param Builder<Holiday> $query
     * @return Builder<Holiday>
     */
    public function scopeBlockingScheduling(Builder $query): Builder
    {
        return $query->where('blocks_scheduling', true);
    }

    /**
     * العطل المتقاطعة مع نطاق معطى (بغضّ الطرفين).
     */
    /**
     * @param Builder<Holiday> $query
     * @return Builder<Holiday>
     */
    public function scopeOverlapping(Builder $query, string|CarbonImmutable $startsOn, string|CarbonImmutable $endsOn): Builder
    {
        $start = $startsOn instanceof CarbonImmutable ? $startsOn : CarbonImmutable::parse($startsOn);
        $end = $endsOn instanceof CarbonImmutable ? $endsOn : CarbonImmutable::parse($endsOn);

        return $query->whereDate('starts_on', '<=', $end->toDateString())
            ->whereDate('ends_on', '>=', $start->toDateString());
    }

    /**
     * عدد الأيام التي تغطيها العطلة (شامل الطرفين).
     */
    public function daysCount(): int
    {
        return (int) $this->starts_on->diffInDays($this->ends_on) + 1;
    }
}
