<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->jsonb('name');
            $table->string('slug', 64)->unique();
            $table->string('logo_path')->nullable();
            $table->string('default_timezone', 64)->default('Africa/Cairo');
            $table->char('default_currency', 3)->default('EGP');
            $table->string('default_locale', 8)->default('ar');
            $table->jsonb('supported_locales')->nullable();
            $table->string('week_starts_on', 16)->default('saturday');
            $table->jsonb('settings')->nullable();
            $table->jsonb('feature_overrides')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
