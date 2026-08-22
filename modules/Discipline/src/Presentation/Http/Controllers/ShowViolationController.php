<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Discipline\Domain\Models\ViolationEvent;
use Modules\Discipline\Presentation\Http\Resources\ViolationEventResource;

/**
 * عرض مخالفة واحدة — قراءة فقط.
 */
final class ShowViolationController extends Controller
{
    public function __invoke(ViolationEvent $violation): ViolationEventResource
    {
        return new ViolationEventResource($violation);
    }
}
