<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->string('code')->unique();
            $table->jsonb('name');
            $table->jsonb('description');
            $table->string('icon_path')->nullable();
            $table->string('tier')->comment('bronze | silver | gold');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();

            $table->index(['organization_id', 'tier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badges');
    }
};
