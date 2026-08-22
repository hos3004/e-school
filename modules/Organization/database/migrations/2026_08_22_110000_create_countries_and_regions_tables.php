<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('iso2', 2)->unique();
            $table->char('iso3', 3);
            $table->jsonb('name');
            $table->string('phone_code', 16);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('regions', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('country_id', 26);
            $table->string('code', 32);
            $table->jsonb('name');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();

            $table->foreign('country_id')->references('id')->on('countries')->restrictOnDelete();
            $table->unique(['country_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
        Schema::dropIfExists('countries');
    }
};
