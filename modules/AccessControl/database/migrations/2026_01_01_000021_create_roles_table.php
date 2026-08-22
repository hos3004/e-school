<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->nullable();
            $table->string('name', 191);
            $table->string('guard_name', 64)->default('web');
            $table->boolean('is_system')->default(false);
            $table->timestampsTz();

            $table->unique(['organization_id', 'name', 'guard_name']);
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
