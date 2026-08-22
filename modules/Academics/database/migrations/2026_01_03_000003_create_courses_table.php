<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('level_id', 26);
            $table->string('code')->unique();
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->unsignedInteger('total_sessions')->nullable();
            $table->jsonb('completion_rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('organization_id');
            $table->index('level_id');

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('level_id')->references('id')->on('levels')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
