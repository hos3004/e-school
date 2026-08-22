<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Notifications\Application\Actions\RetryNotificationAction;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Presentation\Http\Requests\RetryNotificationRequest;
use Modules\Notifications\Presentation\Http\Resources\NotificationOutboxResource;

/**
 * إعادة محاولة رسالة فاشلة.
 */
final class RetryNotificationController extends Controller
{
    public function __construct(
        private readonly RetryNotificationAction $action,
    ) {}

    public function __invoke(RetryNotificationRequest $request, string $outbox): NotificationOutboxResource
    {
        $notification = NotificationOutbox::query()->findOrFail($outbox);

        Gate::authorize('retry', $notification);

        $this->action->execute($notification);

        return new NotificationOutboxResource($notification->refresh());
    }
}
