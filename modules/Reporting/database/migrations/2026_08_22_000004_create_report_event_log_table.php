<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_event_log', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->nullable();
            $table->char('event_id', 26);
            $table->string('name');
            $table->string('module');
            $table->char('actor_id', 26)->nullable();
            $table->char('correlation_id', 26);
            $table->timestampTz('occurred_at');
            $table->jsonb('payload');

            $table->timestampsTz();

            $table->unique('event_id');
            $table->index(['name', 'occurred_at']);
            $table->index(['module', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_event_log');
    }
};
