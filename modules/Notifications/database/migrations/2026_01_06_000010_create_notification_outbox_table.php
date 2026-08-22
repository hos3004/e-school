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
        Schema::create('notification_outbox', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->char('user_id', 26)->index();
            $table->string('category');
            $table->string('channel');
            $table->string('locale', 16);
            $table->string('event_name');
            $table->char('event_id', 26)->index();
            $table->char('correlation_id', 26)->nullable()->index();
            $table->jsonb('subject')->nullable();
            $table->jsonb('body');
            $table->jsonb('payload');
            $table->string('idempotency_key');
            $table->timestampTz('scheduled_for');
            $table->string('status', 32)->index();
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations');
            $table->foreign('user_id')
                ->references('id')
                ->on('users');
        });

        DB::statement(
            'ALTER TABLE notification_outbox '
            .'ADD CONSTRAINT notification_outbox_idempotency_key_unique UNIQUE (idempotency_key)',
        );
        DB::statement(
            'CREATE INDEX notification_outbox_status_scheduled_for_idx '
            .'ON notification_outbox (status, scheduled_for)',
        );
        DB::statement(
            'CREATE INDEX notification_outbox_user_created_at_desc_idx '
            .'ON notification_outbox (user_id, created_at DESC)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_outbox');
    }
};
