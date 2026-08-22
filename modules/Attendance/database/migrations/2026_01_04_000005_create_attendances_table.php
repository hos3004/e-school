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
        Schema::create('attendances', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('session_participant_id', 26)->unique();
            $table->string('status');
            $table->string('derived_status');
            $table->unsignedInteger('attended_minutes')->default(0);
            $table->unsignedInteger('joined_after_minutes')->default(0);
            $table->unsignedInteger('left_before_minutes')->default(0);
            $table->char('confirmed_by', 26)->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->text('override_reason')->nullable();
            $table->timestampsTz();

            $table->foreign('session_participant_id')->references('id')->on('session_participants')->cascadeOnDelete();
            $table->foreign('confirmed_by')->references('id')->on('users')->restrictOnDelete();

            $table->index(['status', 'confirmed_at']);
        });

        DB::statement('ALTER TABLE attendances ADD CONSTRAINT attendances_override_requires_reason CHECK (status = derived_status OR override_reason IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
