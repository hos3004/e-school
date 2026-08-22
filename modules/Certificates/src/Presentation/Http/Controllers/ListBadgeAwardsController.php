<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Certificates\Domain\Models\BadgeAward;
use Modules\Certificates\Presentation\Http\Resources\BadgeAwardResource;

/**
 * قائمة منح الشارات للمؤسسة الحالية.
 */
final class ListBadgeAwardsController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        $awards = BadgeAward::query()
            ->forOrganization((string) auth()->user()->organization_id)
            ->orderByDesc('awarded_at')
            ->paginate(20);

        return BadgeAwardResource::collection($awards);
    }
}
