<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_categories', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->char('program_id', 26)->nullable()->index();
            $table->char('parent_id', 26)->nullable()->index();
            $table->string('code', 32);
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('program_id')->references('id')->on('programs')->nullOnDelete();
            $table->foreign('parent_id')->references('id')->on('program_categories')->nullOnDelete();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('course_category', function (Blueprint $table): void {
            $table->char('course_id', 26);
            $table->char('category_id', 26);
            $table->timestampsTz();

            $table->primary(['course_id', 'category_id']);
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('program_categories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_category');
        Schema::dropIfExists('program_categories');
    }
};
