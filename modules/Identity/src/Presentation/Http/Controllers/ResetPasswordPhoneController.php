<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Identity\Application\Actions\ResetPasswordWithOtp;
use Modules\Identity\Presentation\Http\Requests\ResetPasswordPhoneRequest;

final readonly class ResetPasswordPhoneController
{
    public function __construct(private ResetPasswordWithOtp $action) {}

    public function __invoke(ResetPasswordPhoneRequest $request): JsonResponse
    {
        $this->action->execute(
            organizationId: (string) $request->validated('organization_id'),
            phone: (string) $request->validated('phone'),
            otp: (string) $request->validated('otp'),
            newPassword: (string) $request->validated('password'),
        );

        return response()->json([
            'message' => __('identity::messages.password_changed'),
        ]);
    }
}
