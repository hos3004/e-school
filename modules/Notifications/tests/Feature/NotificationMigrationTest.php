<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;

interface NotificationReversibleMigration
{
    public function up(): void;

    public function down(): void;
}

it('rolls the idempotency migration down and up without deleting duplicate history', function (): void {
    $key = hash('sha256', 'migration-rollback-duplicate');

    NotificationOutbox::factory()->state([
        'idempotency_key' => $key,
        'status' => OutboxStatus::Queued,
    ])->create();
    NotificationOutbox::factory()->state([
        'idempotency_key' => $key,
        'status' => OutboxStatus::Sent,
    ])->create();
    NotificationOutbox::factory()->state([
        'idempotency_key' => $key,
        'status' => OutboxStatus::Suppressed,
    ])->create();

    /** @var Migration&NotificationReversibleMigration $migration */
    $migration = require base_path(
        'modules/Notifications/database/migrations/2026_08_22_000001_allow_suppressed_notification_duplicates.php',
    );

    $migration->down();

    $rolledBack = DB::table('notification_outbox')->orderBy('created_at')->get();

    expect($rolledBack)->toHaveCount(3)
        ->and($rolledBack->pluck('idempotency_key')->unique())->toHaveCount(3)
        ->and($rolledBack->pluck('status')->all())->toContain('pending', 'sent', 'cancelled')
        ->and((int) DB::scalar(<<<'SQL'
            SELECT COUNT(*)
            FROM pg_constraint
            WHERE conname = 'notification_outbox_idempotency_key_unique'
            SQL))->toBe(1);

    $migration->up();

    expect(DB::table('notification_outbox')->count())->toBe(3)
        ->and(DB::table('notification_outbox')->where('status', 'queued')->count())->toBe(1)
        ->and((int) DB::scalar(<<<'SQL'
            SELECT COUNT(*)
            FROM pg_indexes
            WHERE indexname = 'notification_outbox_idempotency_key_idx'
              AND indexdef NOT ILIKE '%UNIQUE%'
            SQL))->toBe(1);
});
