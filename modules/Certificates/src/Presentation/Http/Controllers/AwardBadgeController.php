<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Certificates\Application\Actions\AwardBadgeAction;
use Modules\Certificates\Domain\Models\Badge;
use Modules\Certificates\Presentation\Http\Requests\AwardBadgeRequest;
use Modules\Certificates\Presentation\Http\Resources\BadgeAwardResource;

/**
 * منح شارة لمستخدم.
 */
final class AwardBadgeController extends Controller
{
    public function __construct(
        private readonly AwardBadgeAction $action,
    ) {}

    public function __invoke(AwardBadgeRequest $request, string $badge): BadgeAwardResource
    {
        $badgeModel = Badge::query()->findOrFail($badge);

        Gate::authorize('update', $badgeModel);

        $award = $this->action->execute(
            $badgeModel,
            (string) $request->validated('user_id'),
            $request->validated('reason'),
        );

        return new BadgeAwardResource($award);
    }
}
