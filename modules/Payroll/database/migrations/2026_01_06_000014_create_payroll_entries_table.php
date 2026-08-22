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
        Schema::create('payroll_entries', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->char('payroll_period_id', 26);
            $table->char('staff_profile_id', 26)->index();
            $table->char('session_id', 26)->nullable()->index();
            $table->char('teacher_contract_id', 26)->index();
            $table->string('entry_type');
            $table->string('outcome_key');
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->jsonb('rate_snapshot');
            $table->string('status', 32)->index();
            $table->char('deferred_until_session_id', 26)->nullable()->index();
            $table->jsonb('description')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index(
                ['payroll_period_id', 'staff_profile_id'],
                'payroll_entries_period_staff_idx'
            );

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations');
            $table->foreign('payroll_period_id')
                ->references('id')
                ->on('payroll_periods');
        });

        DB::statement(
            'ALTER TABLE payroll_entries '
            .'ADD CONSTRAINT payroll_entries_session_staff_type_unique '
            .'UNIQUE (session_id, staff_profile_id, entry_type)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entries');
    }
};
