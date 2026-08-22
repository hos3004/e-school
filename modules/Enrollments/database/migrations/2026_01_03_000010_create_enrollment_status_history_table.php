<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_status_history', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('enrollment_id', 26);
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason');
            $table->char('changed_by', 26);
            $table->timestampTz('changed_at');
            $table->jsonb('metadata')->nullable();

            $table->index('enrollment_id');

            $table->foreign('enrollment_id')->references('id')->on('enrollments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_status_history');
    }
};
