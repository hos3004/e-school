<?php

declare(strict_types=1);

namespace Modules\Recordings\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Recordings\Application\Services\RecordingAccessDecision;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Presentation\Http\Resources\RecordingResource;

/**
 * قائمة التسجيلات — محصورة دائمًا بمؤسسة المستخدم.
 */
final class ListRecordingsController extends Controller
{
    public function __construct(private readonly RecordingAccessDecision $access) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Recording::class);

        $recordings = $this->access->scopeVisible(Recording::query(), $request->user())
            ->orderByDesc('available_from')
            ->paginate((int) $request->integer('per_page', 20));

        return RecordingResource::collection($recordings);
    }
}
