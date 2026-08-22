<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('notification_outbox')->where('status', 'pending')->update(['status' => 'queued']);

        DB::statement(
            'ALTER TABLE notification_outbox '
            .'DROP CONSTRAINT IF EXISTS notification_outbox_idempotency_key_unique',
        );

        DB::statement(
            'CREATE INDEX notification_outbox_idempotency_key_idx '
            .'ON notification_outbox (idempotency_key)',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS notification_outbox_idempotency_key_idx');

        // المخطط القديم لا يعرف suppressed؛ نحافظ على أثرها كرسائل ملغاة.
        DB::table('notification_outbox')->where('status', 'suppressed')->update(['status' => 'cancelled']);
        DB::table('notification_outbox')->where('status', 'queued')->update(['status' => 'pending']);

        // قد تسمح نافذة عدم التكرار بأكثر من صف للمفتاح نفسه. نحتفظ
        // بكل الصفوف ونغيّر مفاتيح النسخ اللاحقة قبل استعادة UNIQUE القديم.
        DB::statement(<<<'SQL'
            WITH ranked AS (
                SELECT id,
                       ROW_NUMBER() OVER (
                           PARTITION BY idempotency_key
                           ORDER BY created_at, id
                       ) AS duplicate_rank
                FROM notification_outbox
            )
            UPDATE notification_outbox AS outbox
            SET idempotency_key = LEFT(outbox.idempotency_key, 228) || ':' || outbox.id
            FROM ranked
            WHERE ranked.id = outbox.id
              AND ranked.duplicate_rank > 1
            SQL);

        DB::statement(
            'ALTER TABLE notification_outbox '
            .'ADD CONSTRAINT notification_outbox_idempotency_key_unique UNIQUE (idempotency_key)',
        );
    }
};
