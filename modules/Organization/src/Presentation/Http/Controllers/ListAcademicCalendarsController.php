<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Organization\Domain\Models\AcademicCalendar;
use Modules\Organization\Domain\Models\Organization;
use Modules\Organization\Presentation\Http\Resources\AcademicCalendarResource;

final class ListAcademicCalendarsController
{
    /**
     * @throws AuthorizationException
     */
    public function __invoke(Request $request, Organization $organization): AnonymousResourceCollection
    {
        if (!($request->user()?->can('viewAny', AcademicCalendar::class) ?? false)) {
            throw new AuthorizationException(__('organization::errors.unauthorized'));
        }

        $calendars = AcademicCalendar::query()
            ->forOrganization($organization->id)
            ->orderByDesc('starts_on')
            ->get();

        return AcademicCalendarResource::collection($calendars);
    }
}
