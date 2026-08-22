<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Identity\Application\Actions\UpdatePassword;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Presentation\Http\Requests\UpdatePasswordRequest;
use Modules\Identity\Presentation\Http\Resources\UserResource;

final readonly class UpdatePasswordController
{
    public function __construct(private UpdatePassword $action) {}

    public function __invoke(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updated = $this->action->execute(
            user: $user,
            currentPassword: (string) $request->validated('current_password'),
            newPassword: (string) $request->validated('password'),
        );

        return UserResource::make($updated)->response();
    }
}
