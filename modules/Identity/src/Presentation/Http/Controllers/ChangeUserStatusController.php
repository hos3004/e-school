<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Identity\Application\Actions\ChangeUserStatus;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Presentation\Http\Requests\ChangeUserStatusRequest;
use Modules\Identity\Presentation\Http\Resources\UserResource;

final readonly class ChangeUserStatusController
{
    public function __construct(private ChangeUserStatus $action) {}

    public function __invoke(ChangeUserStatusRequest $request, User $user): JsonResponse
    {
        $updated = $this->action->execute(
            target: $user,
            to: UserStatus::from((string) $request->validated('status')),
            reason: (string) $request->validated('reason'),
        );

        return UserResource::make($updated)->response();
    }
}
