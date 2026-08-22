<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Identity\Application\Actions\RegisterDevice;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Presentation\Http\Requests\RegisterDeviceRequest;
use Modules\Identity\Presentation\Http\Resources\UserDeviceResource;

final readonly class RegisterDeviceController
{
    public function __construct(private RegisterDevice $action) {}

    public function __invoke(RegisterDeviceRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $device = $this->action->execute(
            userId: $user->id,
            attributes: $request->validated(),
        );

        return UserDeviceResource::make($device)
            ->response()
            ->setStatusCode(201);
    }
}
