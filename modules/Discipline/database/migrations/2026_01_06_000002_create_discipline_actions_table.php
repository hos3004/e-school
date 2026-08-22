<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_actions', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('enrollment_id', 26);
            $table->char('triggered_by_event_id', 26);
            $table->string('action', 32);
            $table->integer('threshold_reached');
            $table->char('window_key', 7);
            $table->boolean('is_automatic');
            $table->timestampTz('applied_at');
            $table->char('applied_by', 26)->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index('organization_id', 'discipline_actions_organization_id_index');
            $table->index('enrollment_id', 'discipline_actions_enrollment_id_index');
            $table->index('triggered_by_event_id', 'discipline_actions_triggered_event_index');
            $table->index('applied_by', 'discipline_actions_applied_by_index');

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->restrictOnDelete();
            $table->foreign('triggered_by_event_id')
                ->references('id')
                ->on('violation_events')
                ->restrictOnDelete();
            $table->foreign('applied_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_actions');
    }
};
