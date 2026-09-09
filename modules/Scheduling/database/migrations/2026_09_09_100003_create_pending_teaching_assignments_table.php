<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_teaching_assignments', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            foreach (['organization_id', 'student_profile_id', 'staff_profile_id', 'course_id', 'created_by'] as $column) {
                $table->char($column, 26);
            }
            $table->string('session_type');
            $table->unsignedInteger('duration_minutes');
            $table->text('reason');
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['organization_id', 'student_profile_id', 'staff_profile_id', 'course_id'], 'pending_teaching_assignment_unique');
            $table->index(['organization_id', 'staff_profile_id']);
            foreach (['organization_id' => 'organizations', 'student_profile_id' => 'student_profiles', 'staff_profile_id' => 'staff_profiles', 'course_id' => 'courses', 'created_by' => 'users'] as $column => $target) {
                $table->foreign($column)->references('id')->on($target)->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_teaching_assignments');
    }
};
