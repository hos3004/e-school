<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_obligations', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->char('staff_profile_id', 26)->index();
            $table->char('payroll_period_id', 26)->index();
            $table->string('obligation_type');
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->integer('target_teaching');
            $table->integer('achieved_teaching');
            $table->integer('target_admin');
            $table->integer('achieved_admin');
            $table->integer('target_training');
            $table->integer('achieved_training');
            $table->string('status', 32)->index();
            $table->timestampsTz();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations');
            $table->foreign('payroll_period_id')
                ->references('id')
                ->on('payroll_periods');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_obligations');
    }
};
