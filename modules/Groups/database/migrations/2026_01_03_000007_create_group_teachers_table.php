<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_teachers', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('group_id', 26);
            $table->char('staff_profile_id', 26);
            $table->char('course_id', 26)->nullable();
            $table->string('role');
            $table->date('assigned_from');
            $table->date('assigned_to')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->unique(['group_id', 'staff_profile_id', 'course_id']);
            $table->index('staff_profile_id');
            $table->index('course_id');

            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->foreign('staff_profile_id')->references('id')->on('staff_profiles')->restrictOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_teachers');
    }
};
