<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Reporting\Application\Actions\IngestDomainEventAction;
use Modules\Reporting\Domain\Models\ReportEventLog;
use Shared\Domain\DomainEvent;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

/**
 * حدث تجريبي محلي — يرث DomainEvent بلا الاعتماد على موديول آخر.
 */
final class FakeSourceEvent extends DomainEvent
{
    public function __construct(
        public readonly string $organizationId,
        public readonly string $name = 'fake.source_event',
    ) {
        parent::__construct();
    }

    public function name(): string
    {
        return $this->name;
    }

    public function module(): string
    {
        return 'fake';
    }

    public function payload(): array
    {
        return ['organization_id' => $this->organizationId];
    }
}

it('ingests a new event and returns true', function (): void {
    Event::fake();

    $event = new FakeSourceEvent(Fixtures::organizationId());

    $ingested = app(IngestDomainEventAction::class)->execute($event);

    expect($ingested)->toBeTrue()
        ->and(ReportEventLog::query()->where('event_id', $event->eventId)->exists())->toBeTrue()
        ->and(ReportEventLog::query()->count())->toBe(1);
});

it('rejects re-ingesting the same event id (idempotency)', function (): void {
    Event::fake();

    $event = new FakeSourceEvent(Fixtures::organizationId());
    $action = app(IngestDomainEventAction::class);

    expect($action->execute($event))->toBeTrue()
        ->and($action->execute($event))->toBeFalse()
        ->and(ReportEventLog::query()->count())->toBe(1);
});
