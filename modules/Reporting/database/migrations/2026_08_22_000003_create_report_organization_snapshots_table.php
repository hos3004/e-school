<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_organization_snapshots', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->date('snapshot_date');
            $table->string('period_type', 20)->default('daily');

            $table->unsignedInteger('students_active')->default(0);
            $table->unsignedInteger('students_frozen')->default(0);
            $table->unsignedInteger('teachers_active')->default(0);
            $table->unsignedInteger('sessions_held')->default(0);
            $table->unsignedInteger('sessions_cancelled')->default(0);
            $table->unsignedInteger('attendance_rate_bp')->default(0);

            $table->timestampsTz();

            $table->unique(['organization_id', 'snapshot_date', 'period_type']);
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();

            $table->index(['organization_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_organization_snapshots');
    }
};
