<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_teacher_dashboards', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('staff_profile_id', 26);

            $table->unsignedInteger('sessions_total')->default(0);
            $table->unsignedInteger('sessions_completed')->default(0);
            $table->unsignedInteger('cancellations_by_self')->default(0);
            $table->unsignedInteger('postponements')->default(0);
            // المستحق الصافي بالوحدات الصغرى — قراءة تجميعية من قيود الرواتب.
            $table->unsignedBigInteger('payout_minor')->default(0);
            $table->string('currency', 3)->default('EGP');

            $table->timestampTz('last_session_at')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique('staff_profile_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();

            $table->index(['organization_id', 'payout_minor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_teacher_dashboards');
    }
};
