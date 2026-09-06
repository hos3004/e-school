<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_weekly_slots', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('schedule_id', 26);
            $table->unsignedSmallInteger('weekday');
            $table->time('start_time');
            $table->timestampsTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('schedule_id')->references('id')->on('schedules')->cascadeOnDelete();
            $table->unique(['schedule_id', 'weekday']);
            $table->index(['organization_id', 'schedule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_weekly_slots');
    }
};
