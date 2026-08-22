<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_participants', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('session_id', 26);
            $table->char('student_profile_id', 26);
            $table->char('enrollment_id', 26);
            $table->string('join_url_token');
            $table->timestampTz('invited_at');
            $table->timestampTz('first_joined_at')->nullable();
            $table->timestampTz('last_left_at')->nullable();
            $table->unsignedInteger('attended_minutes')->default(0);
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('session_id')->references('id')->on('sessions')->cascadeOnDelete();
            $table->foreign('student_profile_id')->references('id')->on('student_profiles')->restrictOnDelete();
            $table->foreign('enrollment_id')->references('id')->on('enrollments')->restrictOnDelete();

            $table->unique(['session_id', 'student_profile_id']);
            $table->index('join_url_token');
            $table->index('enrollment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_participants');
    }
};
