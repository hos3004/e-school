<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_eligibility', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('program_id', 26)->unique();
            $table->jsonb('countries')->default('[]');
            $table->jsonb('regions')->default('[]');
            $table->smallInteger('age_from')->nullable();
            $table->smallInteger('age_to')->nullable();
            $table->string('gender', 16)->nullable();
            $table->boolean('manual_approval_required')->default(true);
            $table->string('teacher_gender_rule', 16)->default(config('admission.matching.default_gender_rule', 'any'));
            $table->boolean('requires_individual_sessions')->default(false);
            $table->timestampsTz();

            $table->foreign('program_id')->references('id')->on('programs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_eligibility');
    }
};
