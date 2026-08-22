<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->string('code')->unique();
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->unsignedInteger('duration_weeks')->nullable();
            $table->unsignedInteger('default_session_minutes');
            $table->unsignedBigInteger('default_rate')->nullable();
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('organization_id');

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
