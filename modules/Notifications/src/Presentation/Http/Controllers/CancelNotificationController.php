<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Notifications\Application\Actions\CancelNotificationAction;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Presentation\Http\Requests\CancelNotificationRequest;
use Modules\Notifications\Presentation\Http\Resources\NotificationOutboxResource;

/**
 * إلغاء رسالة في الانتظار.
 */
final class CancelNotificationController extends Controller
{
    public function __construct(
        private readonly CancelNotificationAction $action,
    ) {}

    public function __invoke(CancelNotificationRequest $request, string $outbox): NotificationOutboxResource
    {
        $notification = NotificationOutbox::query()->findOrFail($outbox);

        Gate::authorize('cancel', $notification);

        $this->action->execute(
            $notification,
            (string) $request->validated('reason'),
            (string) $request->user()?->getAuthIdentifier(),
        );

        return new NotificationOutboxResource($notification->refresh());
    }
}
