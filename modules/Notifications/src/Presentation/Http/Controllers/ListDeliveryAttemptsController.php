<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Notifications\Domain\Models\NotificationDeliveryAttempt;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Presentation\Http\Resources\NotificationDeliveryAttemptResource;

/**
 * سجل محاولات تسليم رسالة — للتشخيص والتدقيق.
 */
final class ListDeliveryAttemptsController extends Controller
{
    public function __invoke(string $outbox): AnonymousResourceCollection
    {
        $notification = NotificationOutbox::query()->findOrFail($outbox);

        Gate::authorize('view', $notification);

        $attempts = NotificationDeliveryAttempt::query()
            ->forOutbox($notification->id)
            ->orderByDesc('attempt_number')
            ->get();

        return NotificationDeliveryAttemptResource::collection($attempts);
    }
}
