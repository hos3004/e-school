<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_attempts', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('assessment_id', 26);
            $table->char('student_profile_id', 26);
            $table->char('reactivation_request_id', 26)->nullable();
            $table->integer('attempt_number');
            $table->timestampTz('started_at');
            $table->timestampTz('submitted_at')->nullable();
            $table->integer('score')->nullable();
            $table->boolean('passed')->nullable();
            $table->char('graded_by', 26)->nullable();
            $table->timestampTz('graded_at')->nullable();
            $table->jsonb('answers');
            $table->timestampTz('created_at');

            $table->foreign('assessment_id')->references('id')->on('assessments')->restrictOnDelete();
            $table->foreign('student_profile_id')->references('id')->on('student_profiles')->restrictOnDelete();
            $table->foreign('graded_by')->references('id')->on('users')->restrictOnDelete();
            // reactivation_request_id: بدون FK — الجدول يملكه موديول Discipline (حدود الموديولات)

            $table->index(['assessment_id', 'student_profile_id', 'attempt_number']);
            $table->index('student_profile_id');
            $table->index('reactivation_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_attempts');
    }
};
