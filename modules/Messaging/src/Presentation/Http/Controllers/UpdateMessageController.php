<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Messaging\Application\Actions\EditMessageAction;
use Modules\Messaging\Domain\Models\Message;
use Modules\Messaging\Presentation\Http\Requests\UpdateMessageRequest;
use Modules\Messaging\Presentation\Http\Resources\MessageResource;

/**
 * تعديل رسالة.
 */
final class UpdateMessageController extends Controller
{
    public function __construct(
        private readonly EditMessageAction $action,
    ) {}

    public function __invoke(UpdateMessageRequest $request, Message $message): MessageResource
    {
        Gate::authorize('update', $message);

        $edited = $this->action->execute(
            message: $message,
            editorUserId: (string) $request->user()->getAuthIdentifier(),
            newBody: $request->string('body')->toString(),
        );

        return new MessageResource($edited);
    }
}
