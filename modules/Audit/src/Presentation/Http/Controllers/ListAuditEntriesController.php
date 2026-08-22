<?php

declare(strict_types=1);

namespace Modules\Audit\Presentation\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Audit\Domain\Contracts\AuditQueryService;
use Modules\Audit\Presentation\Http\Requests\ListAuditEntriesRequest;
use Modules\Audit\Presentation\Http\Resources\AuditLogResource;

/**
 * GET /api/audit-entries — تصفّح القيود عبر عقد القراءة (DTOs لا models).
 *
 * متحكم رفيع: تحقق الطلب ← خدمة الاستعلام ← مجموعة موارد.
 */
final readonly class ListAuditEntriesController
{
    public function __construct(
        private AuditQueryService $queryService,
    ) {}

    public function __invoke(ListAuditEntriesRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $organizationId = $filters['organization_id'] ?? null;
        unset($filters['organization_id'], $filters['per_page'], $filters['page']);

        $paginator = $this->queryService->paginateForOrganization(
            organizationId: $organizationId,
            filters: $filters,
            perPage: (int) ($request->validated('per_page') ?? config('audit.per_page', 50)),
            page: (int) ($request->validated('page') ?? 1),
        );

        return AuditLogResource::collection($paginator);
    }
}
