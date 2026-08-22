<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Presentation\Http\Resources\NotificationOutboxResource;

/**
 * قائمة إشعارات المستخدم الحالي — «إشعاراتي».
 */
final class ListNotificationsController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', NotificationOutbox::class);

        $notifications = NotificationOutbox::query()
            ->forUser((string) auth()->id())
            ->where('channel', Channel::InApp)
            ->orderByDesc('created_at')
            ->paginate((int) config('notifications.pagination.per_page', 25));

        return NotificationOutboxResource::collection($notifications);
    }
}
