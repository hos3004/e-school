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
        Schema::create('schedules', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('group_id', 26)->nullable();
            $table->char('student_profile_id', 26)->nullable();
            $table->char('course_id', 26);
            $table->char('staff_profile_id', 26);
            $table->string('session_type');
            $table->text('rrule');
            $table->time('start_time');
            $table->unsignedInteger('duration_minutes');
            $table->string('timezone');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('materialized_until');
            $table->boolean('is_active')->default(true);
            $table->char('created_by', 26);
            $table->timestampsTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('group_id')->references('id')->on('groups')->restrictOnDelete();
            $table->foreign('student_profile_id')->references('id')->on('student_profiles')->restrictOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->restrictOnDelete();
            $table->foreign('staff_profile_id')->references('id')->on('staff_profiles')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();

            $table->index(['organization_id', 'is_active']);
            $table->index('group_id');
            $table->index('student_profile_id');
            $table->index('staff_profile_id');
            $table->index('course_id');
        });

        DB::statement('ALTER TABLE schedules ADD CONSTRAINT schedules_target_check CHECK (group_id IS NOT NULL OR student_profile_id IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
