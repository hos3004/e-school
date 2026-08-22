<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('certificate_template_id', 26)->nullable();
            $table->char('student_profile_id', 26);
            $table->char('program_id', 26)->nullable();
            $table->char('enrollment_id', 26)->nullable();
            $table->string('serial_number')->unique();
            $table->jsonb('title');
            $table->timestampTz('issued_at');
            $table->char('issued_by', 26);
            $table->timestampTz('expires_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('certificate_template_id')->references('id')->on('certificate_templates')->restrictOnDelete();
            $table->foreign('student_profile_id')->references('id')->on('student_profiles')->restrictOnDelete();
            $table->foreign('program_id')->references('id')->on('programs')->restrictOnDelete();
            $table->foreign('enrollment_id')->references('id')->on('enrollments')->restrictOnDelete();
            $table->foreign('issued_by')->references('id')->on('users')->restrictOnDelete();

            $table->index(['organization_id', 'student_profile_id']);
            $table->index('issued_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
