<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Presentation\Http\Resources\UserResource;

final readonly class MeController
{
    public function __invoke(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return UserResource::make($user)->response();
    }
}
