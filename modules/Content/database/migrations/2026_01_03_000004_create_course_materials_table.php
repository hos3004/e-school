<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_materials', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('course_id', 26);
            $table->jsonb('title');
            $table->string('type');
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->string('external_url')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestampTz('visible_from')->nullable();
            $table->timestampTz('visible_to')->nullable();
            $table->char('uploaded_by', 26)->nullable();
            $table->timestampTz('created_at')->nullable();
            $table->softDeletesTz();

            $table->index('course_id');

            $table->foreign('course_id')->references('id')->on('courses')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_materials');
    }
};
