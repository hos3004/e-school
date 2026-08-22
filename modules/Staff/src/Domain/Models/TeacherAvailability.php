<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class TeacherAvailability extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'teacher_availability';

    protected $fillable = [
        'staff_profile_id',
        'weekday',
        'start_time',
        'end_time',
        'timezone',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'start_time' => 'string',
            'end_time' => 'string',
            'effective_from' => 'immutable_datetime',
            'effective_to' => 'immutable_datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForProfile(Builder $query, string $staffProfileId): Builder
    {
        return $query->where('staff_profile_id', $staffProfileId);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOnWeekday(Builder $query, int $weekday): Builder
    {
        return $query->where('weekday', $weekday);
    }

    /**
     * السجلات السارية بتاريخ معيّن.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActiveOn(Builder $query, CarbonImmutable|string $date): Builder
    {
        $date = $date instanceof CarbonImmutable ? $date : CarbonImmutable::parse($date);

        return $query
            ->whereDate('effective_from', '<=', $date)
            ->where(
                fn (Builder $q): Builder => $q
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date),
            );
    }
}
