<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->string('name', 191);
            $table->string('guard_name', 64)->default('web');
            $table->string('module', 64)->nullable();
            $table->jsonb('description')->nullable();
            $table->timestampsTz();

            $table->unique(['name', 'guard_name']);
            $table->index('module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
