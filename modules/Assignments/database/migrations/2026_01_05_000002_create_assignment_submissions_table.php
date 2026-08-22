<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_submissions', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('assignment_id', 26);
            $table->char('student_profile_id', 26);
            $table->timestampTz('submitted_at')->nullable();
            $table->boolean('is_late');
            $table->text('content')->nullable();
            $table->jsonb('attachments');
            $table->integer('score')->nullable();
            $table->text('feedback')->nullable();
            $table->char('graded_by', 26)->nullable();
            $table->timestampTz('graded_at')->nullable();
            $table->string('status')->comment('pending | submitted | late | graded');
            $table->timestampsTz();

            $table->foreign('assignment_id')->references('id')->on('assignments')->restrictOnDelete();
            $table->foreign('student_profile_id')->references('id')->on('student_profiles')->restrictOnDelete();
            $table->foreign('graded_by')->references('id')->on('users')->restrictOnDelete();

            $table->unique(['assignment_id', 'student_profile_id']);
            $table->index('student_profile_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
    }
};
