<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Identity\Application\Actions\IssuePasswordResetToken;
use Modules\Identity\Presentation\Http\Requests\ForgotPasswordRequest;

final readonly class ForgotPasswordController
{
    public function __construct(private IssuePasswordResetToken $action) {}

    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        $this->action->execute((string) $request->validated('email'));

        // الاستجابة واحدة دائمًا — لا نكشف وجود البريد أم لا.
        return response()->json([
            'message' => __('identity::messages.reset_link_sent'),
        ]);
    }
}
