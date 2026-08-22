<?php

declare(strict_types=1);

namespace Shared\Concerns;

use Shared\Domain\DomainEvent;

/**
 * يجمع الأحداث داخل الكيان ثم ينشرها دفعة واحدة بعد نجاح المعاملة،
 * حتى لا يستمع أحد لحدث عن تغيير تم التراجع عنه.
 */
trait RecordsDomainEvents
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    protected function recordEvent(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    public function publishRecordedEvents(): void
    {
        foreach ($this->releaseEvents() as $event) {
            event($event);
        }
    }
}
