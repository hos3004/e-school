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
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('teacher_contracts', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('staff_profile_id', 26);
            $table->string('basis');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->bigInteger('base_amount')->nullable();
            $table->char('currency', 3)->nullable();
            $table->integer('monthly_target_sessions')->nullable();
            $table->integer('target_admin_tasks')->nullable();
            $table->integer('target_training_sessions')->nullable();
            $table->jsonb('terms')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('staff_profile_id')->references('id')->on('staff_profiles')->restrictOnDelete();
        });

        DB::statement(
            'ALTER TABLE teacher_contracts ADD CONSTRAINT teacher_contracts_effective_period_check '
            .'CHECK (effective_to IS NULL OR effective_to > effective_from)'
        );

        DB::statement(
            'ALTER TABLE teacher_contracts ADD CONSTRAINT teacher_contracts_no_overlap_excl '
            .'EXCLUDE USING gist (staff_profile_id WITH =, daterange(effective_from, effective_to) WITH &&)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_contracts');
    }
};
