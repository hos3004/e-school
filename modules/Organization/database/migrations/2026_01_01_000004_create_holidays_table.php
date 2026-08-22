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
        Schema::create('holidays', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('academic_calendar_id', 26)->nullable();
            $table->jsonb('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('source', 32)->default('manual');
            $table->boolean('blocks_scheduling')->default(false);
            $table->timestampsTz();
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('academic_calendar_id')->references('id')->on('academic_calendars')->nullOnDelete();
            $table->index(['organization_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
