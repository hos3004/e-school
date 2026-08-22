<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Reporting\Application\Actions\RecordOrganizationSnapshotAction;
use Modules\Reporting\Presentation\Http\Requests\StoreOrganizationSnapshotRequest;
use Modules\Reporting\Presentation\Http\Resources\OrganizationSnapshotResource;

/**
 * بناء/تحديث لقطة تنظيمية لليوم.
 */
final class StoreOrganizationSnapshotController extends Controller
{
    public function __construct(
        private readonly RecordOrganizationSnapshotAction $action,
    ) {}

    public function __invoke(StoreOrganizationSnapshotRequest $request): JsonResponse
    {
        $snapshot = $this->action->execute($request->validated());

        return OrganizationSnapshotResource::make($snapshot)
            ->response()
            ->setStatusCode(201);
    }
}
