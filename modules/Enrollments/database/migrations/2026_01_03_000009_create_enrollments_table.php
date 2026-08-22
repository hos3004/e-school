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
        Schema::create('enrollments', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('student_profile_id', 26);
            $table->char('program_id', 26);
            $table->string('status');
            $table->timestampTz('applied_at');
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('withdrawn_at')->nullable();
            $table->char('current_level_id', 26)->nullable();
            $table->timestampTz('frozen_at')->nullable();
            $table->string('frozen_reason')->nullable();
            $table->string('freeze_type')->nullable();
            $table->date('expected_return_date')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['organization_id', 'status']);
            $table->index('student_profile_id');
            $table->index('program_id');
            $table->index('current_level_id');

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('student_profile_id')->references('id')->on('student_profiles')->restrictOnDelete();
            $table->foreign('program_id')->references('id')->on('programs')->restrictOnDelete();
            $table->foreign('current_level_id')->references('id')->on('levels')->restrictOnDelete();
        });

        DB::statement(
            'CREATE UNIQUE INDEX enrollments_student_program_active_unique '.
            'ON enrollments (student_profile_id, program_id) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
