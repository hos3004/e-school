<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_status_history', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('session_id', 26);
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->char('changed_by', 26);
            $table->timestampTz('changed_at');
            $table->jsonb('metadata')->nullable();

            $table->foreign('session_id')->references('id')->on('sessions')->cascadeOnDelete();
            $table->foreign('changed_by')->references('id')->on('users')->restrictOnDelete();

            $table->index(['session_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_status_history');
    }
};
