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
        Schema::create('payroll_periods', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->integer('year');
            $table->integer('month');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 32)->index();
            $table->timestampTz('calculated_at')->nullable();
            $table->char('reviewed_by', 26)->nullable()->index();
            $table->timestampTz('reviewed_at')->nullable();
            $table->char('approved_by', 26)->nullable()->index();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->jsonb('totals');
            $table->timestampsTz();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations');
            $table->foreign('reviewed_by')
                ->references('id')
                ->on('users');
            $table->foreign('approved_by')
                ->references('id')
                ->on('users');
        });

        DB::statement(
            'ALTER TABLE payroll_periods '
            .'ADD CONSTRAINT payroll_periods_organization_year_month_unique '
            .'UNIQUE (organization_id, year, month)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
