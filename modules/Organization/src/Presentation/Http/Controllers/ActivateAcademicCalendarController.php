<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Organization\Application\Actions\ActivateAcademicCalendar;
use Modules\Organization\Domain\Models\AcademicCalendar;
use Modules\Organization\Presentation\Http\Resources\AcademicCalendarResource;

final class ActivateAcademicCalendarController
{
    public function __construct(
        private readonly ActivateAcademicCalendar $activateCalendar,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(Request $request, AcademicCalendar $calendar): JsonResponse
    {
        if (!($request->user()?->can('activate', $calendar) ?? false)) {
            throw new AuthorizationException(__('organization::errors.unauthorized'));
        }

        $calendar = $this->activateCalendar->execute($calendar);

        return AcademicCalendarResource::make($calendar)->response();
    }
}
