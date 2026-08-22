<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recording_views', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('recording_id', 26);
            $table->char('user_id', 26);
            $table->timestampTz('viewed_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('action');

            $table->foreign('recording_id')->references('id')->on('recordings')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();

            $table->index(['recording_id', 'viewed_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recording_views');
    }
};
