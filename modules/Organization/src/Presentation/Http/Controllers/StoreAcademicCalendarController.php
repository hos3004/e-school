<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Organization\Application\Actions\ActivateAcademicCalendar;
use Modules\Organization\Application\Actions\CreateAcademicCalendar;
use Modules\Organization\Domain\Models\Organization;
use Modules\Organization\Presentation\Http\Requests\StoreAcademicCalendarRequest;
use Modules\Organization\Presentation\Http\Resources\AcademicCalendarResource;

final class StoreAcademicCalendarController
{
    public function __construct(
        private readonly CreateAcademicCalendar $createCalendar,
        private readonly ActivateAcademicCalendar $activateCalendar,
    ) {}

    public function __invoke(StoreAcademicCalendarRequest $request, Organization $organization): JsonResponse
    {
        $data = $request->validated();

        $calendar = $this->createCalendar->execute(
            organization: $organization,
            name: $data['name'],
            startsOn: $data['starts_on'],
            endsOn: $data['ends_on'],
            isActive: false,
        );

        if ((bool) ($data['is_active'] ?? false)) {
            $calendar = $this->activateCalendar->execute($calendar);
        }

        return AcademicCalendarResource::make($calendar)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
