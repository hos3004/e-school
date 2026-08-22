<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('course_id', 26);
            $table->char('group_id', 26)->nullable();
            $table->char('staff_profile_id', 26);
            $table->jsonb('title');
            $table->jsonb('instructions');
            $table->jsonb('attachments');
            $table->timestampTz('assigned_at');
            $table->timestampTz('due_at');
            $table->integer('max_score');
            $table->boolean('allows_late');
            $table->integer('late_penalty_percent');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->restrictOnDelete();
            $table->foreign('group_id')->references('id')->on('groups')->restrictOnDelete();
            $table->foreign('staff_profile_id')->references('id')->on('staff_profiles')->restrictOnDelete();

            $table->index(['organization_id', 'course_id']);
            $table->index('group_id');
            $table->index('staff_profile_id');
            $table->index('due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
