<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Organization\Application\Actions\UpdateOrganization;
use Modules\Organization\Domain\Models\Organization;
use Modules\Organization\Presentation\Http\Requests\UpdateOrganizationRequest;
use Modules\Organization\Presentation\Http\Resources\OrganizationResource;

final class UpdateOrganizationController
{
    public function __construct(
        private readonly UpdateOrganization $updateOrganization,
    ) {}

    public function __invoke(UpdateOrganizationRequest $request, Organization $organization): JsonResponse
    {
        $organization = $this->updateOrganization->execute($organization, $request->validated());

        return OrganizationResource::make($organization)->response();
    }
}
