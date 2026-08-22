<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Identity\Application\Actions\ResetPassword;
use Modules\Identity\Presentation\Http\Requests\ResetPasswordRequest;
use Modules\Identity\Presentation\Http\Resources\UserResource;

final readonly class ResetPasswordController
{
    public function __construct(private ResetPassword $action) {}

    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        // خرق قاعدة العمل يُترجَم عالميًا إلى 422 برسالة مترجمة.
        $user = $this->action->execute(
            email: (string) $request->validated('email'),
            token: (string) $request->validated('token'),
            newPassword: (string) $request->validated('password'),
        );

        return UserResource::make($user)->response();
    }
}
