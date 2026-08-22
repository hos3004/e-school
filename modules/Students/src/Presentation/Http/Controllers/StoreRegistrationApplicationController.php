<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Students\Application\Actions\CreateRegistrationApplicationAction;
use Modules\Students\Presentation\Http\Requests\StoreRegistrationApplicationRequest;
use Modules\Students\Presentation\Http\Resources\RegistrationApplicationResource;

final class StoreRegistrationApplicationController extends Controller
{
    public function __construct(private readonly CreateRegistrationApplicationAction $action) {}

    public function __invoke(StoreRegistrationApplicationRequest $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = (string) data_get($user, 'organization_id');
        $userId = (string) $user->getAuthIdentifier();

        abort_if($organizationId === '' || $userId === '', 403);

        $application = $this->action->execute(
            $request->validated(),
            $organizationId,
            $userId,
        );

        return RegistrationApplicationResource::make($application)
            ->response()
            ->setStatusCode(201);
    }
}
