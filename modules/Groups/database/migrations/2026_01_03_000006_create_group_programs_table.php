<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_programs', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('group_id', 26);
            $table->char('program_id', 26);
            $table->timestampTz('created_at')->nullable();

            $table->unique(['group_id', 'program_id']);
            $table->index('program_id');

            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->foreign('program_id')->references('id')->on('programs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_programs');
    }
};
