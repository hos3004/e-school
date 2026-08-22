<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Presentation\Http\Resources\NotificationOutboxResource;

/**
 * قائمة إشعارات المستخدم الحالي — «إشعاراتي».
 */
final class ListNotificationsController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('listOwn', NotificationOutbox::class);

        $userId = (string) $request->user()?->getAuthIdentifier();
        $organizationId = (string) data_get($request->user(), 'organization_id');

        $notifications = NotificationOutbox::query()
            ->forOrganization($organizationId)
            ->forUser($userId)
            ->where('channel', Channel::InApp->value)
            ->where('status', OutboxStatus::Sent)
            ->orderByDesc('created_at')
            ->paginate((int) config('notifications.pagination.per_page', 25));

        return NotificationOutboxResource::collection($notifications);
    }
}
