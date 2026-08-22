<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Presentation\Http\Resources\NotificationOutboxResource;

/**
 * عرض رسالة واحدة — للمالك أو لمن يملك صلاحية العرض الإداري.
 */
final class ShowNotificationController extends Controller
{
    public function __invoke(string $outbox): NotificationOutboxResource
    {
        $notification = NotificationOutbox::query()->findOrFail($outbox);

        $user = auth()->user();

        if (!$user->can('view', $notification) && !$user->can('viewOwn', $notification)) {
            abort(403);
        }

        return new NotificationOutboxResource($notification);
    }
}
