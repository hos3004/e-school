<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_password_reset_tokens', function (Blueprint $table): void {
            $table->char('user_id', 26)->primary();
            $table->char('organization_id', 26);
            $table->string('token_hash', 255);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->index(['organization_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_password_reset_tokens');
    }
};
