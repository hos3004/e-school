<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Organization\Domain\Models\Organization;
use Modules\Organization\Presentation\Http\Resources\OrganizationResource;
use Illuminate\Auth\Access\AuthorizationException;

final class ShowOrganizationController
{
    /**
     * @throws AuthorizationException
     */
    public function __invoke(Request $request, Organization $organization): OrganizationResource
    {
        $this->authorizeView($request, $organization);

        return OrganizationResource::make($organization);
    }

    private function authorizeView(Request $request, Organization $organization): void
    {
        if (! ($request->user()?->can('view', $organization) ?? false)) {
            throw new AuthorizationException(__('organization::errors.unauthorized'));
        }
    }
}
