<?php

declare(strict_types=1);

namespace Modules\Audit\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * حُذفت القيود الأقدم من مدة الاحتفاظ المحددة في config.
 *
 * الحذف هنا هو الاستثناء الوحيد على قاعدة «لا حذف»، ويقتصر على
 * مهمة التقادم الدورية التي يملكها هذا الموديول وحده.
 */
final class AuditEntriesPruned extends DomainEvent
{
    public function __construct(
        public readonly int $prunedCount,
        public readonly string $beforeDate,
        ?string $actorId = null,
    ) {
        parent::__construct($actorId);
    }

    public function name(): string
    {
        return 'audit.entries_pruned';
    }

    public function module(): string
    {
        return 'audit';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'pruned_count' => $this->prunedCount,
            'before_date' => $this->beforeDate,
        ];
    }
}
