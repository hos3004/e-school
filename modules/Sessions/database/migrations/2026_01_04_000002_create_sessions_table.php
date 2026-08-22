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
        Schema::create('sessions', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('schedule_id', 26)->nullable();
            $table->char('group_id', 26)->nullable();
            $table->char('course_id', 26);
            $table->char('staff_profile_id', 26);
            $table->char('substitute_for_staff_id', 26)->nullable();
            $table->char('makeup_for_session_id', 26)->nullable();
            $table->string('session_type');
            $table->string('status');
            $table->timestampTz('scheduled_start');
            $table->timestampTz('scheduled_end');
            $table->timestampTz('actual_start')->nullable();
            $table->timestampTz('actual_end')->nullable();
            $table->jsonb('title');
            $table->text('notes')->nullable();
            $table->char('cancelled_by', 26)->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestampTz('finalized_at')->nullable();
            $table->char('finalized_by', 26)->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('schedule_id')->references('id')->on('schedules')->restrictOnDelete();
            $table->foreign('group_id')->references('id')->on('groups')->restrictOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->restrictOnDelete();
            $table->foreign('staff_profile_id')->references('id')->on('staff_profiles')->restrictOnDelete();
            $table->foreign('substitute_for_staff_id')->references('id')->on('staff_profiles')->restrictOnDelete();
            $table->foreign('cancelled_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('finalized_by')->references('id')->on('users')->restrictOnDelete();

            $table->index(['organization_id', 'status', 'scheduled_start']);
            $table->index(['staff_profile_id', 'scheduled_start']);
            $table->index('schedule_id');
            $table->index('group_id');
            $table->index('course_id');
            $table->index('makeup_for_session_id');
        });

        Schema::table('sessions', function (Blueprint $table): void {
            $table->foreign('makeup_for_session_id')->references('id')->on('sessions')->restrictOnDelete();
        });

        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        DB::statement("ALTER TABLE sessions ADD COLUMN time_range tstzrange GENERATED ALWAYS AS (tstzrange(scheduled_start, scheduled_end, '[)')) STORED");

        DB::statement("ALTER TABLE sessions ADD CONSTRAINT sessions_no_teacher_double_booking EXCLUDE USING gist (staff_profile_id WITH =, time_range WITH &&) WHERE (status NOT IN ('cancelled_by_student', 'cancelled_by_teacher', 'cancelled_by_school', 'postponed') AND deleted_at IS NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
