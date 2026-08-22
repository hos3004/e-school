<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Organization\Application\Actions\AddHoliday;
use Modules\Organization\Domain\Models\Organization;
use Modules\Organization\Presentation\Http\Requests\StoreHolidayRequest;
use Modules\Organization\Presentation\Http\Resources\HolidayResource;

final class StoreHolidayController
{
    public function __construct(
        private readonly AddHoliday $addHoliday,
    ) {}

    public function __invoke(StoreHolidayRequest $request, Organization $organization): JsonResponse
    {
        $data = $request->validated();

        $holiday = $this->addHoliday->execute(
            organizationId: $organization->id,
            name: $data['name'],
            startsOn: $data['starts_on'],
            endsOn: $data['ends_on'],
            academicCalendarId: $data['academic_calendar_id'] ?? null,
            blocksScheduling: (bool) ($data['blocks_scheduling'] ?? true),
        );

        return HolidayResource::make($holiday)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
