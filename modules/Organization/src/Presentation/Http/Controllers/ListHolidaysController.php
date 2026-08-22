<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Organization\Domain\Models\Holiday;
use Modules\Organization\Domain\Models\Organization;
use Modules\Organization\Presentation\Http\Resources\HolidayResource;

final class ListHolidaysController
{
    /**
     * @throws AuthorizationException
     */
    public function __invoke(Request $request, Organization $organization): AnonymousResourceCollection
    {
        if (!($request->user()?->can('viewAny', Holiday::class) ?? false)) {
            throw new AuthorizationException(__('organization::errors.unauthorized'));
        }

        $holidays = Holiday::query()
            ->forOrganization($organization->id)
            ->orderBy('starts_on')
            ->get();

        return HolidayResource::collection($holidays);
    }
}
