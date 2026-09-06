<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $schedule_id
 * @property int $weekday
 * @property string $start_time
 */
final class ScheduleWeeklySlot extends Model
{
    use HasUlid;

    protected $table = 'schedule_weekly_slots';

    protected $fillable = [
        'organization_id',
        'schedule_id',
        'weekday',
        'start_time',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'int',
        ];
    }
}
