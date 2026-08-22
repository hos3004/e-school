<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_webhook_deliveries', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('connection_id', 26);
            $table->string('direction', 20);
            $table->string('event_type');
            $table->string('status');
            $table->unsignedInteger('attempts')->default(0);
            $table->jsonb('payload')->nullable();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('next_retry_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampsTz();

            $table->foreign('connection_id')->references('id')->on('integration_connections')->cascadeOnDelete();

            $table->index(['connection_id', 'status']);
            $table->index(['status', 'next_retry_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_webhook_deliveries');
    }
};
