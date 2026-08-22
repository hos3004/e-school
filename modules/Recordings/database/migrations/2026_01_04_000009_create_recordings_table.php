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
        Schema::create('recordings', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('session_id', 26);
            $table->char('classroom_id', 26);
            $table->string('provider');
            $table->string('external_recording_id');
            $table->string('status');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('disk');
            $table->text('path');
            $table->text('thumbnail_path')->nullable();
            $table->string('archive_driver')->nullable();
            $table->text('archive_path')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampTz('available_from');
            $table->timestampTz('expires_at');
            $table->softDeletesTz();
            $table->char('deleted_by', 26)->nullable();
            $table->text('deletion_reason')->nullable();
            $table->timestampsTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('session_id')->references('id')->on('sessions')->restrictOnDelete();
            $table->foreign('classroom_id')->references('id')->on('classrooms')->restrictOnDelete();
            $table->foreign('deleted_by')->references('id')->on('users')->restrictOnDelete();

            $table->index(['session_id', 'status']);
            $table->index(['provider', 'external_recording_id']);
            $table->index('organization_id');
        });

        DB::statement('CREATE INDEX recordings_expires_at_active_idx ON recordings (expires_at) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('recordings');
    }
};
