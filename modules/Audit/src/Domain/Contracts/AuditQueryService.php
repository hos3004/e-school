<?php

declare(strict_types=1);

namespace Modules\Audit\Domain\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Audit\Application\Queries\AuditEntryData;

/**
 * عقد القراءة العام لموديول Audit.
 *
 * الموديولات الأخرى تقرأ القيود عبر هذه الواجهة فقط، وتستلم DTOs —
 * أبدًا Eloquent models. التنفيذ في Infrastructure\Persistence.
 */
interface AuditQueryService
{
    /**
     * قيود مؤسسة معينة بعدّة تصفية اختيارية.
     *
     * @param  array{
     *     action?: string,
     *     auditable_type?: string,
     *     auditable_id?: string,
     *     actor_id?: string,
     *     correlation_id?: string
     * }  $filters
     * @return LengthAwarePaginator<AuditEntryData>
     */
    public function paginateForOrganization(
        ?string $organizationId,
        array $filters = [],
        int $perPage = 50,
        int $page = 1,
    ): LengthAwarePaginator;
}
