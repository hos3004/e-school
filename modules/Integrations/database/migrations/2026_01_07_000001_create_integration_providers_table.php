<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_providers', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->string('key')->unique();
            $table->jsonb('name');
            $table->string('category', 50)->index();
            $table->string('driver')->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('default_settings')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['is_active', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_providers');
    }
};
