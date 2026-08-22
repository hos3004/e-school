<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Discipline\Domain\Models\ReactivationRequest;
use Modules\Discipline\Presentation\Http\Resources\ReactivationRequestResource;

/**
 * فهرس طلبات إعادة التفعيل — الإدارة ترى كل مؤسستها،
 * والطالب لا يرى إلا طلباته عبر تصفية الملكية.
 */
final class ListReactivationRequestsController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $organizationId = (string) $request->user()->organization_id;
        $canViewAny = $request->user()->can('viewAny', ReactivationRequest::class);

        $requests = ReactivationRequest::query()
            ->forOrganization($organizationId)
            ->when(
                !$canViewAny,
                fn ($query) => $query->requestedBy((string) $request->user()->getAuthIdentifier()),
            )
            ->orderByDesc('created_at')
            ->paginate(min(max((int) $request->query('per_page', 15), 1), 100));

        return ReactivationRequestResource::collection($requests);
    }
}
