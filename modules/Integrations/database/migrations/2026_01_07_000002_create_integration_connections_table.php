<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_connections', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('provider_id', 26);
            $table->string('status');
            $table->jsonb('credentials')->nullable();
            $table->jsonb('settings')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('disabled_at')->nullable();
            $table->timestampTz('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'provider_id']);
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('provider_id')->references('id')->on('integration_providers')->restrictOnDelete();

            $table->index(['status', 'activated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_connections');
    }
};
