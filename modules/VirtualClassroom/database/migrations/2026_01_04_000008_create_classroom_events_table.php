<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_events', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('classroom_id', 26);
            $table->string('event_type');
            $table->string('external_user_id')->nullable();
            $table->char('user_id', 26)->nullable();
            $table->timestampTz('occurred_at');
            $table->jsonb('payload')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('classroom_id')->references('id')->on('classrooms')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();

            $table->index(['classroom_id', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_events');
    }
};
