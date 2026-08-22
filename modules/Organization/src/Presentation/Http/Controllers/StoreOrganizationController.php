<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Organization\Application\Actions\CreateOrganization;
use Modules\Organization\Presentation\Http\Requests\StoreOrganizationRequest;
use Modules\Organization\Presentation\Http\Resources\OrganizationResource;

final class StoreOrganizationController
{
    public function __construct(
        private readonly CreateOrganization $createOrganization,
    ) {}

    public function __invoke(StoreOrganizationRequest $request): JsonResponse
    {
        $organization = $this->createOrganization->execute($request->validated());

        return OrganizationResource::make($organization)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
