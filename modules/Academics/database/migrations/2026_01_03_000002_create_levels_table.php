<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('levels', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('program_id', 26);
            $table->string('code');
            $table->jsonb('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampTz('created_at')->nullable();

            $table->unique(['program_id', 'code']);
            $table->index('program_id');

            $table->foreign('program_id')->references('id')->on('programs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('levels');
    }
};
