<?php

declare(strict_types=1);

namespace Modules\Audit\Tests\Unit;

use Illuminate\Support\Facades\Event;
use Modules\Audit\Application\Actions\PruneExpiredEntriesAction;
use Modules\Audit\Domain\Events\AuditEntriesPruned;
use Modules\Audit\Domain\Models\AuditLog;
use Modules\Audit\Tests\Support\RefreshAuditDatabase;
use Tests\TestCase;

/**
 * قواعد تقادم دفتر التدقيق.
 */
final class PruneExpiredEntriesActionTest extends TestCase
{
    use RefreshAuditDatabase;

    public function test_deletes_only_entries_older_than_configured_window(): void
    {
        config(['audit.retention_days' => 30]);

        Event::fake([AuditEntriesPruned::class]);

        AuditLog::factory()->create(['created_at' => now()->utc()->subDays(40)]);
        AuditLog::factory()->create(['created_at' => now()->utc()->subDays(10)]);

        $pruned = app(PruneExpiredEntriesAction::class)->execute();

        self::assertSame(1, $pruned);
        self::assertSame(1, AuditLog::query()->count());
        self::assertTrue(AuditLog::query()->first()->created_at->greaterThan(now()->utc()->subDays(30)));

        Event::assertDispatched(
            AuditEntriesPruned::class,
            fn (AuditEntriesPruned $event): bool => $event->prunedCount === 1,
        );
    }

    public function test_dispatches_no_event_when_nothing_expired(): void
    {
        config(['audit.retention_days' => 3650]);

        Event::fake([AuditEntriesPruned::class]);

        AuditLog::factory()->count(2)->create();

        self::assertSame(0, app(PruneExpiredEntriesAction::class)->execute());
        self::assertSame(2, AuditLog::query()->count());

        Event::assertNothingDispatched();
    }

    public function test_retention_window_floors_at_one_day(): void
    {
        config(['audit.retention_days' => 0]);

        AuditLog::factory()->create(['created_at' => now()->utc()->subHours(2)]);

        self::assertSame(0, app(PruneExpiredEntriesAction::class)->execute());
    }
}
