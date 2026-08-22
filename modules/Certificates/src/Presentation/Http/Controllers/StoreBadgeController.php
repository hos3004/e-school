<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Certificates\Application\Actions\CreateBadgeAction;
use Modules\Certificates\Presentation\Http\Requests\StoreBadgeRequest;
use Modules\Certificates\Presentation\Http\Resources\BadgeResource;

/**
 * إنشاء شارة.
 */
final class StoreBadgeController extends Controller
{
    public function __construct(
        private readonly CreateBadgeAction $action,
    ) {}

    public function __invoke(StoreBadgeRequest $request): BadgeResource
    {
        $badge = $this->action->execute($request->validated());

        return new BadgeResource($badge);
    }
}
