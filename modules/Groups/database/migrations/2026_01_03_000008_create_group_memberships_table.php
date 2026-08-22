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
        Schema::create('group_memberships', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('group_id', 26);
            $table->char('student_profile_id', 26);
            $table->timestampTz('joined_at');
            $table->timestampTz('left_at')->nullable();
            $table->string('status');
            $table->timestampTz('created_at')->nullable();

            $table->index('student_profile_id');
            $table->index(['group_id', 'status']);

            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->foreign('student_profile_id')->references('id')->on('student_profiles')->restrictOnDelete();
        });

        DB::statement(
            'CREATE UNIQUE INDEX group_memberships_group_student_active_unique '.
            'ON group_memberships (group_id, student_profile_id) WHERE left_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('group_memberships');
    }
};
