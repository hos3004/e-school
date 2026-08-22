<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Discipline\Domain\Models\ReactivationRequest;
use Modules\Discipline\Presentation\Http\Resources\ReactivationRequestResource;

/**
 * عرض طلب إعادة تفعيل واحد — قراءة فقط.
 */
final class ShowReactivationRequestController extends Controller
{
    public function __invoke(ReactivationRequest $reactivation): ReactivationRequestResource
    {
        return new ReactivationRequestResource($reactivation);
    }
}
