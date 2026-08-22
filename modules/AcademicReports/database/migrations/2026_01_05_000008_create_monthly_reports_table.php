<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_reports', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('student_profile_id', 26);
            $table->char('enrollment_id', 26);
            $table->integer('period_year');
            $table->integer('period_month');
            $table->jsonb('metrics')->comment('مجمّع الحضور والدرجات والمخالفات');
            $table->text('supervisor_summary')->nullable();
            $table->string('status')->comment('draft | approved | sent');
            $table->char('approved_by', 26)->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('student_profile_id')->references('id')->on('student_profiles')->restrictOnDelete();
            $table->foreign('enrollment_id')->references('id')->on('enrollments')->restrictOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->restrictOnDelete();

            $table->unique(['student_profile_id', 'period_year', 'period_month']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_reports');
    }
};
