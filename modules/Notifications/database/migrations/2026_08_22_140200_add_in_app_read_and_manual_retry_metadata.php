<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_outbox', function (Blueprint $table): void {
            $table->timestampTz('read_at')->nullable();
            $table->char('last_manual_retry_by', 26)->nullable()->index();
            $table->timestampTz('last_manual_retry_at')->nullable();
        });

        // الفهرس الجزئي يبقي عدّاد الجرس سريعًا دون تضخيم فهارس بقية القنوات.
        DB::statement(
            'CREATE INDEX notification_outbox_unread_in_app_idx '
            .'ON notification_outbox (organization_id, user_id, created_at DESC) '
            ."WHERE channel = 'in_app' AND read_at IS NULL",
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS notification_outbox_unread_in_app_idx');

        Schema::table('notification_outbox', function (Blueprint $table): void {
            $table->dropIndex(['last_manual_retry_by']);
            $table->dropColumn([
                'read_at',
                'last_manual_retry_by',
                'last_manual_retry_at',
            ]);
        });
    }
};
