<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_reports', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('session_id', 26);
            $table->char('staff_profile_id', 26);
            $table->text('topics_covered')->nullable();
            $table->text('homework_assigned')->nullable();
            $table->text('general_notes')->nullable();
            $table->text('supervisor_private_note')->nullable();
            $table->text('next_session_plan')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->boolean('is_late');
            $table->timestampsTz();

            $table->foreign('session_id')->references('id')->on('sessions')->restrictOnDelete();
            $table->foreign('staff_profile_id')->references('id')->on('staff_profiles')->restrictOnDelete();

            $table->unique('session_id');
            $table->index(['staff_profile_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_reports');
    }
};
