<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_settings', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->string('key', 128);
            $table->jsonb('value')->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->timestampsTz();
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->unique(['organization_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_settings');
    }
};
