<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;

/** عدّاد خفيف تستخدمه الواجهة كـpolling fallback عندما لا يتوفر Reverb. */
final class UnreadNotificationCountController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('listOwn', NotificationOutbox::class);

        $count = NotificationOutbox::query()
            ->forOrganization((string) data_get($request->user(), 'organization_id'))
            ->forUser((string) $request->user()?->getAuthIdentifier())
            ->where('channel', Channel::InApp->value)
            ->where('status', OutboxStatus::Sent)
            ->whereNull('read_at')
            ->count();

        return response()->json(['data' => ['unread_count' => $count]]);
    }
}
