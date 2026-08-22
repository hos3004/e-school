<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Identity\Application\Actions\RegisterUser;
use Modules\Identity\Presentation\Http\Requests\RegisterUserRequest;
use Modules\Identity\Presentation\Http\Resources\UserResource;

final readonly class RegisterUserController
{
    public function __construct(private RegisterUser $action) {}

    public function __invoke(RegisterUserRequest $request): JsonResponse
    {
        $user = $this->action->execute($request->validated());

        return UserResource::make($user)
            ->response()
            ->setStatusCode(201);
    }
}
