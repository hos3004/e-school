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
        Schema::table('teacher_availability', function (Blueprint $table): void {
            $table->string('approval_status', 16)->default('pending')->index();
            $table->char('approved_by', 26)->nullable();
            $table->timestampTz('approved_at')->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE teacher_availability
            ADD CONSTRAINT teacher_availability_approval_status_check
            CHECK (approval_status IN ('pending', 'approved'))
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE teacher_availability DROP CONSTRAINT IF EXISTS teacher_availability_approval_status_check');

        Schema::table('teacher_availability', function (Blueprint $table): void {
            $table->dropColumn(['approval_status', 'approved_by', 'approved_at']);
        });
    }
};
