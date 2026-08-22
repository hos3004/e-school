<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_rates', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('teacher_contract_id', 26);
            $table->string('scope');
            $table->char('program_id', 26)->nullable();
            $table->char('course_id', 26)->nullable();
            $table->string('session_type')->nullable();
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['teacher_contract_id', 'scope', 'effective_from']);

            $table->foreign('teacher_contract_id')->references('id')->on('teacher_contracts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_rates');
    }
};
