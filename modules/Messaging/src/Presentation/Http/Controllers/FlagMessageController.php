<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Messaging\Application\Actions\FlagMessageAction;
use Modules\Messaging\Domain\Models\Message;
use Modules\Messaging\Presentation\Http\Requests\FlagMessageRequest;
use Modules\Messaging\Presentation\Http\Resources\MessageResource;

/**
 * وسم رسالة كمخالفة.
 */
final class FlagMessageController extends Controller
{
    public function __construct(
        private readonly FlagMessageAction $action,
    ) {}

    public function __invoke(FlagMessageRequest $request, Message $message): MessageResource
    {
        Gate::authorize('flag', $message);

        $flagged = $this->action->execute(
            message: $message,
            moderatorUserId: (string) $request->user()->getAuthIdentifier(),
            reason: $request->string('reason')->toString(),
        );

        return new MessageResource($flagged);
    }
}
