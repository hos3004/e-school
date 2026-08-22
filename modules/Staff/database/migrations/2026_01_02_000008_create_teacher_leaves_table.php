<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_leaves', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('staff_profile_id', 26);
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('reason')->nullable();
            $table->string('status');
            $table->char('approved_by', 26)->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['staff_profile_id', 'starts_at']);

            $table->foreign('staff_profile_id')->references('id')->on('staff_profiles')->cascadeOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_leaves');
    }
};
