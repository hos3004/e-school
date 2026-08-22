<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_student_dashboards', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('enrollment_id', 26);
            $table->char('student_profile_id', 26);

            $table->unsignedInteger('sessions_total')->default(0);
            $table->unsignedInteger('sessions_attended')->default(0);
            $table->unsignedInteger('sessions_missed')->default(0);
            // نسبة الحضور من أصل 10000 (basis points) — لا float في التقارير.
            $table->unsignedInteger('attendance_rate_bp')->default(0);
            $table->unsignedInteger('violations_count')->default(0);
            $table->unsignedInteger('freezes_count')->default(0);

            $table->timestampTz('last_session_at')->nullable();
            $table->timestampTz('last_violation_at')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique('enrollment_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();

            $table->index(['organization_id', 'attendance_rate_bp']);
            $table->index('student_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_student_dashboards');
    }
};
