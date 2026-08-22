<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('assessment_id', 26);
            $table->string('type')->comment('mcq | true_false | short_answer | essay');
            $table->jsonb('body');
            $table->jsonb('options')->nullable();
            $table->jsonb('correct_answer')->nullable();
            $table->integer('score');
            $table->integer('sort_order');
            $table->timestampTz('created_at');

            $table->foreign('assessment_id')->references('id')->on('assessments')->restrictOnDelete();

            $table->index(['assessment_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
