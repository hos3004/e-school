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
        DB::statement('ALTER TABLE teacher_availability DROP CONSTRAINT IF EXISTS teacher_availability_approval_status_check');

        Schema::table('teacher_availability', function (Blueprint $table): void {
            $table->char('decided_by', 26)->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_reason')->nullable();

            $table->foreign('decided_by')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE teacher_availability
            ADD CONSTRAINT teacher_availability_approval_status_check
            CHECK (approval_status IN ('pending', 'approved', 'rejected'))
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE teacher_availability DROP CONSTRAINT IF EXISTS teacher_availability_approval_status_check');

        Schema::table('teacher_availability', function (Blueprint $table): void {
            $table->dropForeign(['decided_by']);
            $table->dropColumn(['decided_by', 'decided_at', 'decision_reason']);
        });

        DB::table('teacher_availability')
            ->where('approval_status', 'rejected')
            ->update(['approval_status' => 'pending']);

        DB::statement(<<<'SQL'
            ALTER TABLE teacher_availability
            ADD CONSTRAINT teacher_availability_approval_status_check
            CHECK (approval_status IN ('pending', 'approved'))
            SQL);
    }
};
