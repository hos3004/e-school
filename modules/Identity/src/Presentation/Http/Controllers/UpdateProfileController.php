<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Identity\Application\Actions\UpdateUserProfile;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Presentation\Http\Requests\UpdateProfileRequest;
use Modules\Identity\Presentation\Http\Resources\UserResource;

final readonly class UpdateProfileController
{
    public function __construct(private UpdateUserProfile $action) {}

    public function __invoke(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updated = $this->action->execute($user, $request->validated());

        return UserResource::make($updated)->response();
    }
}
