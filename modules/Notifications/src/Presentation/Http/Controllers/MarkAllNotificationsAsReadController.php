<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Notifications\Application\Actions\MarkAllNotificationsAsReadAction;
use Modules\Notifications\Domain\Models\NotificationOutbox;

/** تعليم جميع إشعارات الجرس الخاصة بالمستخدم الحالي كمقروءة. */
final class MarkAllNotificationsAsReadController extends Controller
{
    public function __construct(
        private readonly MarkAllNotificationsAsReadAction $action,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('markAllAsRead', NotificationOutbox::class);

        $markedCount = $this->action->execute(
            (string) $request->user()?->getAuthIdentifier(),
            (string) data_get($request->user(), 'organization_id'),
        );

        return response()->json([
            'message' => __('notifications::messages.marked_all_as_read'),
            'data' => ['marked_count' => $markedCount],
        ]);
    }
}
