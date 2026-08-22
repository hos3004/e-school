<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Certificates\Application\Actions\UpdateBadgeAction;
use Modules\Certificates\Domain\Models\Badge;
use Modules\Certificates\Presentation\Http\Requests\UpdateBadgeRequest;
use Modules\Certificates\Presentation\Http\Resources\BadgeResource;

/**
 * تعديل شارة.
 */
final class UpdateBadgeController extends Controller
{
    public function __construct(
        private readonly UpdateBadgeAction $action,
    ) {}

    public function __invoke(UpdateBadgeRequest $request, string $badge): BadgeResource
    {
        $badgeModel = Badge::query()->findOrFail($badge);

        Gate::authorize('update', $badgeModel);

        $this->action->execute($badgeModel, $request->validated());

        return new BadgeResource($badgeModel->refresh());
    }
}
