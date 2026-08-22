<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_availability', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('staff_profile_id', 26);
            $table->smallInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('timezone');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['staff_profile_id', 'weekday']);

            $table->foreign('staff_profile_id')->references('id')->on('staff_profiles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_availability');
    }
};
