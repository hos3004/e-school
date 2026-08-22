<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Actions;

use Modules\Reporting\Domain\Models\ReportEventLog;
use Shared\Domain\DomainEvent;
use Shared\Support\Transaction;

/**
 * إدخال حدث خارجي في سجل Reporting — بوابة الإسقاط.
 *
 * القاعدة الحاكمة: فرادة event_id تجعل المعالجة idempotent. الحدث
 * المُدخَل سابقًا لا يُعاد إدخاله، وتُرجع العملية false ليتوقف المستمع.
 *
 * الترتيب: حراس ← معاملة ← (لا أحداث تُنشر هنا — السجل append-only).
 */
final readonly class IngestDomainEventAction
{
    public function __construct(
        private Transaction $transaction,
    ) {}

    /**
     * @return bool هل أُدخل الحدث الآن (true) أم كان مُدخلاً سابقًا (false)؟
     */
    public function execute(DomainEvent $event): bool
    {
        $alreadyIngested = ReportEventLog::query()
            ->where('event_id', $event->eventId)
            ->exists();

        if ($alreadyIngested) {
            return false;
        }

        $this->transaction->run(function () use ($event): void {
            ReportEventLog::query()->create([
                'organization_id' => $event->payload()['organization_id'] ?? null,
                'event_id' => $event->eventId,
                'name' => $event->name(),
                'module' => $event->module(),
                'actor_id' => $event->actorId,
                'correlation_id' => $event->correlationId,
                'occurred_at' => $event->occurredAt,
                'payload' => $event->payload(),
            ]);
        });

        return true;
    }
}
