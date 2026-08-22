<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('session_id', 26)->unique();
            $table->string('provider');
            $table->string('external_id');
            $table->jsonb('external_meta')->nullable();
            $table->string('moderator_secret');
            $table->string('attendee_secret');
            $table->timestampTz('created_remote_at');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->unsignedInteger('max_concurrent_participants')->default(0);
            $table->string('health_status')->default('unknown');
            $table->text('last_error')->nullable();
            $table->timestampsTz();

            $table->foreign('session_id')->references('id')->on('sessions')->restrictOnDelete();

            $table->index(['provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classrooms');
    }
};
