<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Guardians\Application\Actions\CreateGuardianProfile;
use Modules\Guardians\Presentation\Http\Requests\StoreGuardianProfileRequest;
use Modules\Guardians\Presentation\Http\Resources\GuardianProfileResource;

final class StoreGuardianProfileController
{
    public function __construct(
        private readonly CreateGuardianProfile $action,
    ) {}

    public function __invoke(StoreGuardianProfileRequest $request): JsonResponse
    {
        $profile = $this->action->execute($request->validated());

        return GuardianProfileResource::make($profile)
            ->response()
            ->setStatusCode(201);
    }
}
