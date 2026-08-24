<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Identity\Application\Actions\IssuePhonePasswordResetOtp;
use Modules\Identity\Presentation\Http\Requests\ForgotPasswordPhoneRequest;

final readonly class ForgotPasswordPhoneController
{
    public function __construct(private IssuePhonePasswordResetOtp $action) {}

    public function __invoke(ForgotPasswordPhoneRequest $request): JsonResponse
    {
        $this->action->execute(
            organizationId: (string) $request->validated('organization_id'),
            phone: (string) $request->validated('phone'),
        );

        return response()->json([
            'message' => __('identity::messages.phone_reset_requested'),
        ]);
    }
}
