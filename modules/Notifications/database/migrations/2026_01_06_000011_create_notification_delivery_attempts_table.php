<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_delivery_attempts', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->char('outbox_id', 26)->index();
            $table->integer('attempt_number');
            $table->timestampTz('attempted_at');
            $table->jsonb('provider_response')->nullable();
            $table->boolean('succeeded');
            $table->text('error')->nullable();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations');
            $table->foreign('outbox_id')
                ->references('id')
                ->on('notification_outbox');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_attempts');
    }
};
