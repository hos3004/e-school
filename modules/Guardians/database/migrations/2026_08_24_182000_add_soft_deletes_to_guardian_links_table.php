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
        Schema::table('guardian_links', function (Blueprint $table): void {
            $table->softDeletesTz();
        });

        DB::statement('ALTER TABLE guardian_links DROP CONSTRAINT IF EXISTS guardian_links_guardian_profile_id_student_profile_id_unique');
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX guardian_links_active_guardian_student_unique
            ON guardian_links (guardian_profile_id, student_profile_id)
            WHERE deleted_at IS NULL
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS guardian_links_active_guardian_student_unique');
        DB::table('guardian_links')->whereNotNull('deleted_at')->delete();
        DB::statement(<<<'SQL'
            ALTER TABLE guardian_links
            ADD CONSTRAINT guardian_links_guardian_profile_id_student_profile_id_unique
            UNIQUE (guardian_profile_id, student_profile_id)
            SQL);

        Schema::table('guardian_links', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
