<?php

declare(strict_types=1);

namespace Modules\Audit\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Audit\Application\Actions\RecordAuditEntryAction;
use Modules\Audit\Domain\Enums\AuditActorType;
use Modules\Audit\Presentation\Http\Requests\RecordAuditEntryRequest;
use Modules\Audit\Presentation\Http\Resources\AuditLogResource;

/**
 * POST /api/audit-entries — تسجيل قيدة تدقيق.
 *
 * متحكم رفيع: يتحقق الطلب ثم يستدعي الإجراء ويعيد المورد. لا منطق عمل هنا.
 */
final readonly class StoreAuditEntryController
{
    public function __construct(
        private RecordAuditEntryAction $action,
    ) {}

    public function __invoke(RecordAuditEntryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $entry = $this->action->execute(
            organizationId: $validated['organization_id'] ?? null,
            actorId: (string) $request->user()?->getAuthIdentifier(),
            actorType: AuditActorType::User,
            action: (string) $validated['action'],
            auditableType: (string) $validated['auditable_type'],
            auditableId: $validated['auditable_id'] ?? null,
            oldValues: $validated['old_values'] ?? null,
            newValues: $validated['new_values'] ?? null,
            reason: $validated['reason'] ?? null,
            actingForUserId: $validated['acting_for_user_id'] ?? null,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            correlationId: $request->header('X-Correlation-Id'),
        );

        return AuditLogResource::make($entry)
            ->response()
            ->setStatusCode(201);
    }
}
