<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_report_students', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('session_report_id', 26);
            $table->char('student_profile_id', 26);
            $table->smallInteger('participation');
            $table->smallInteger('performance');
            $table->smallInteger('commitment');
            $table->text('strengths')->nullable();
            $table->text('weaknesses')->nullable();
            $table->text('note')->nullable();

            $table->foreign('session_report_id')->references('id')->on('session_reports')->cascadeOnDelete();
            $table->foreign('student_profile_id')->references('id')->on('student_profiles')->restrictOnDelete();

            $table->index('student_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_report_students');
    }
};
