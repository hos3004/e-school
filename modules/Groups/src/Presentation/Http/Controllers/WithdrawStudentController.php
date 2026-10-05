<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Groups\Application\Actions\WithdrawStudentAction;
use Modules\Groups\Domain\Models\GroupMembership;
use Modules\Groups\Presentation\Http\Requests\WithdrawStudentRequest;
use Modules\Groups\Presentation\Http\Resources\GroupMembershipResource;

/**
 * خروج طالب من مجموعة مع تسجيل السبب.
 */
final class WithdrawStudentController extends Controller
{
    public function __construct(
        private readonly WithdrawStudentAction $action,
    ) {}

    public function __invoke(WithdrawStudentRequest $request, GroupMembership $membership): JsonResponse
    {
        $membership = $this->action->execute(
            $membership,
            (string) $request->validated('reason'),
            (string) $request->user()->getAuthIdentifier(),
        );

        return GroupMembershipResource::make($membership)->response();
    }
}
