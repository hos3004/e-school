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
        Schema::create('violation_events', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('enrollment_id', 26);
            $table->char('student_profile_id', 26);
            $table->char('session_id', 26)->nullable();
            $table->string('type', 32);
            $table->timestampTz('occurred_at');
            $table->char('window_key', 7);
            $table->boolean('is_countable')->default(true);
            $table->char('waived_by', 26)->nullable();
            $table->timestampTz('waived_at')->nullable();
            $table->text('waiver_reason')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index('organization_id', 'violation_events_organization_id_index');
            $table->index('enrollment_id', 'violation_events_enrollment_id_index');
            $table->index('student_profile_id', 'violation_events_student_profile_id_index');
            $table->index('session_id', 'violation_events_session_id_index');
            $table->index('waived_by', 'violation_events_waived_by_index');

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->restrictOnDelete();
            $table->foreign('waived_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        DB::statement(
            'CREATE INDEX violation_events_enrollment_window_countable_index '
            .'ON violation_events (enrollment_id, window_key) '
            .'WHERE is_countable AND waived_at IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('violation_events');
    }
};
