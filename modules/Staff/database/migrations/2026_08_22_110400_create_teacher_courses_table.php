<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_courses', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->foreignUlid('staff_profile_id')->constrained('staff_profiles')->cascadeOnDelete();
            $table->char('course_id', 26)->index();
            $table->timestampTz('qualified_at')->useCurrent();
            $table->char('qualified_by', 26);
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['staff_profile_id', 'course_id']);
            $table->foreign('qualified_by')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_courses');
    }
};
