<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Organization\Domain\Events\HolidayRemoved;
use Modules\Organization\Domain\Models\Holiday;

/**
 * إزالة عطلة — حذف منطقي يحفظ الأثر في التدقيق.
 */
final readonly class RemoveHoliday
{
    public function execute(Holiday $holiday): void
    {
        DB::transaction(function () use ($holiday): void {
            $holiday->delete();
        });

        Event::dispatch(new HolidayRemoved(
            holidayId: $holiday->id,
            organizationId: $holiday->organization_id,
        ));
    }
}
