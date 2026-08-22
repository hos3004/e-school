<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Organization\Application\Actions\CloseAcademicCalendar;
use Modules\Organization\Domain\Models\AcademicCalendar;
use Modules\Organization\Presentation\Http\Resources\AcademicCalendarResource;

final class CloseAcademicCalendarController
{
    public function __construct(
        private readonly CloseAcademicCalendar $closeCalendar,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(Request $request, AcademicCalendar $calendar): JsonResponse
    {
        if (! ($request->user()?->can('close', $calendar) ?? false)) {
            throw new AuthorizationException(__('organization::errors.unauthorized'));
        }

        $calendar = $this->closeCalendar->execute($calendar);

        return AcademicCalendarResource::make($calendar)->response();
    }
}
