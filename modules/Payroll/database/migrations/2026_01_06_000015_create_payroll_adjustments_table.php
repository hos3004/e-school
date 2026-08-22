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
        Schema::create('payroll_adjustments', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->char('payroll_period_id', 26)->index();
            $table->char('staff_profile_id', 26)->index();
            $table->string('type');
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->text('reason');
            $table->char('references_period_id', 26)->nullable()->index();
            $table->char('proposed_by', 26)->index();
            $table->timestampTz('proposed_at');
            $table->char('approved_by', 26)->nullable()->index();
            $table->timestampTz('approved_at')->nullable();
            $table->char('rejected_by', 26)->nullable()->index();
            $table->timestampTz('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations');
            $table->foreign('payroll_period_id')
                ->references('id')
                ->on('payroll_periods');
            $table->foreign('references_period_id')
                ->references('id')
                ->on('payroll_periods');
            $table->foreign('proposed_by')
                ->references('id')
                ->on('users');
            $table->foreign('approved_by')
                ->references('id')
                ->on('users');
            $table->foreign('rejected_by')
                ->references('id')
                ->on('users');
        });

        DB::statement(
            'ALTER TABLE payroll_adjustments '
            .'ADD CONSTRAINT payroll_adjustments_approval_separation_check '
            .'CHECK (approved_by IS NULL OR approved_by <> proposed_by)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
    }
};
