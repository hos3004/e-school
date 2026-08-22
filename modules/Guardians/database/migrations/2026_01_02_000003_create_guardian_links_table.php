<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_links', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('guardian_profile_id', 26);
            $table->char('student_profile_id', 26);
            $table->string('relationship');
            $table->boolean('is_primary')->default(false);
            $table->boolean('can_act_for')->default(false);
            $table->jsonb('visible_sections')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();

            $table->unique(['guardian_profile_id', 'student_profile_id']);
            $table->index(['student_profile_id', 'is_primary']);

            $table->foreign('guardian_profile_id')->references('id')->on('guardian_profiles')->cascadeOnDelete();
            $table->foreign('student_profile_id')->references('id')->on('student_profiles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_links');
    }
};
