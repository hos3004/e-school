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
        Schema::table('postponement_requests', function (Blueprint $table): void {
            $table->char('organization_id', 26)->nullable()->after('id');
            $table->boolean('requires_admin_review')->default(false)->after('status');
        });

        DB::statement(<<<'SQL'
            UPDATE postponement_requests AS requests
            SET organization_id = sessions.organization_id
            FROM sessions
            WHERE sessions.id = requests.session_id
              AND requests.organization_id IS NULL
        SQL);
        DB::statement('ALTER TABLE postponement_requests ALTER COLUMN organization_id SET NOT NULL');

        Schema::table('postponement_requests', function (Blueprint $table): void {
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->index(['organization_id', 'status', 'created_at'], 'postponements_organization_status_index');
        });

        DB::statement('ALTER TABLE schedules DROP CONSTRAINT IF EXISTS schedules_target_check');
        DB::statement(<<<'SQL'
            ALTER TABLE schedules
            ADD CONSTRAINT schedules_target_check
            CHECK ((group_id IS NOT NULL) <> (student_profile_id IS NOT NULL))
        SQL);

        DB::statement('ALTER TABLE sessions DROP CONSTRAINT IF EXISTS sessions_no_teacher_double_booking');
        DB::statement(<<<'SQL'
            ALTER TABLE sessions
            ADD CONSTRAINT sessions_no_teacher_double_booking
            EXCLUDE USING gist (staff_profile_id WITH =, time_range WITH &&)
            WHERE (
                status NOT IN (
                    'cancelled_by_student', 'cancelled_by_teacher',
                    'cancelled_by_school', 'postponed', 'superseded'
                )
                AND deleted_at IS NULL
            )
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE sessions
            ADD CONSTRAINT sessions_no_group_double_booking
            EXCLUDE USING gist (group_id WITH =, time_range WITH &&)
            WHERE (
                group_id IS NOT NULL
                AND status NOT IN (
                    'cancelled_by_student', 'cancelled_by_teacher',
                    'cancelled_by_school', 'postponed', 'superseded'
                )
                AND deleted_at IS NULL
            )
        SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX sessions_schedule_occurrence_unique
            ON sessions (schedule_id, scheduled_start)
            WHERE schedule_id IS NOT NULL
              AND status <> 'superseded'
              AND deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS sessions_schedule_occurrence_unique');
        DB::statement('ALTER TABLE sessions DROP CONSTRAINT IF EXISTS sessions_no_group_double_booking');
        DB::statement('ALTER TABLE sessions DROP CONSTRAINT IF EXISTS sessions_no_teacher_double_booking');
        DB::statement(<<<'SQL'
            ALTER TABLE sessions
            ADD CONSTRAINT sessions_no_teacher_double_booking
            EXCLUDE USING gist (staff_profile_id WITH =, time_range WITH &&)
            WHERE (
                status NOT IN (
                    'cancelled_by_student', 'cancelled_by_teacher',
                    'cancelled_by_school', 'postponed'
                )
                AND deleted_at IS NULL
            )
        SQL);

        DB::statement('ALTER TABLE schedules DROP CONSTRAINT IF EXISTS schedules_target_check');
        DB::statement(<<<'SQL'
            ALTER TABLE schedules
            ADD CONSTRAINT schedules_target_check
            CHECK (group_id IS NOT NULL OR student_profile_id IS NOT NULL)
        SQL);

        Schema::table('postponement_requests', function (Blueprint $table): void {
            $table->dropIndex('postponements_organization_status_index');
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
            $table->dropColumn('requires_admin_review');
        });
    }
};
