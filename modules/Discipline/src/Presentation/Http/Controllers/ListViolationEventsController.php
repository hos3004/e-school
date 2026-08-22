<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Discipline\Domain\Models\ViolationEvent;
use Modules\Discipline\Presentation\Http\Resources\ViolationEventResource;

/**
 * فهرس مخالفات مؤسسة المستخدم — قراءة فقط مع تصفية اختيارية بنافذة.
 */
final class ListViolationEventsController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $organizationId = (string) $request->user()->organization_id;

        $violations = ViolationEvent::query()
            ->forOrganization($organizationId)
            ->when(
                filled($request->query('window_key')),
                fn ($query) => $query->inWindow((string) $request->query('window_key')),
            )
            ->when(
                filled($request->query('enrollment_id')),
                fn ($query) => $query->forEnrollment((string) $request->query('enrollment_id')),
            )
            ->orderByDesc('occurred_at')
            ->paginate(min(max((int) $request->query('per_page', 15), 1), 100));

        return ViolationEventResource::collection($violations);
    }
}
