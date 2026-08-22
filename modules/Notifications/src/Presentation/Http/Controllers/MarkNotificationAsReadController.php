<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Notifications\Application\Actions\MarkNotificationAsReadAction;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Presentation\Http\Resources\NotificationOutboxResource;

/** تعليم إشعار الجرس الخاص بالمستخدم الحالي كمقروء. */
final class MarkNotificationAsReadController extends Controller
{
    public function __construct(
        private readonly MarkNotificationAsReadAction $action,
    ) {}

    public function __invoke(Request $request, string $outbox): NotificationOutboxResource
    {
        $userId = (string) $request->user()?->getAuthIdentifier();
        $organizationId = (string) data_get($request->user(), 'organization_id');

        $notification = NotificationOutbox::query()
            ->forOrganization($organizationId)
            ->forUser($userId)
            ->where('channel', Channel::InApp->value)
            ->where('status', OutboxStatus::Sent)
            ->findOrFail($outbox);

        Gate::authorize('markAsRead', $notification);

        return new NotificationOutboxResource(
            $this->action->execute($notification, $userId, $organizationId),
        );
    }
}
