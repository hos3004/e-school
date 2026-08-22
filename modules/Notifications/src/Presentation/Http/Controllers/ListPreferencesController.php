<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Notifications\Domain\Models\NotificationPreference;
use Modules\Notifications\Presentation\Http\Resources\NotificationPreferenceResource;

/**
 * قائمة تفضيلات المستخدم الحالي.
 */
final class ListPreferencesController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', NotificationPreference::class);

        $preferences = NotificationPreference::query()
            ->forUser((string) auth()->id())
            ->orderBy('category')
            ->orderBy('channel')
            ->get();

        return NotificationPreferenceResource::collection($preferences);
    }
}
