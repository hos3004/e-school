<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('course_id', 26)->nullable();
            $table->string('type')->comment('quiz | exam | placement | reactivation');
            $table->jsonb('title');
            $table->jsonb('instructions');
            $table->integer('total_score');
            $table->integer('passing_score');
            $table->integer('duration_minutes')->nullable();
            $table->integer('max_attempts');
            $table->timestampTz('available_from');
            $table->timestampTz('available_to');
            $table->char('created_by', 26);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();

            $table->index(['organization_id', 'type']);
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
