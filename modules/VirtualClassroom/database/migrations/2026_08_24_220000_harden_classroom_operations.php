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
        Schema::table('classrooms', function (Blueprint $table): void {
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('provision_attempts')->default(0);
            $table->timestampTz('last_error_at')->nullable();
            $table->softDeletesTz();
        });

        DB::statement('ALTER TABLE classrooms ALTER COLUMN external_id DROP NOT NULL');
        DB::statement('ALTER TABLE classrooms ALTER COLUMN moderator_secret DROP NOT NULL');
        DB::statement('ALTER TABLE classrooms ALTER COLUMN attendee_secret DROP NOT NULL');
        DB::statement('ALTER TABLE classrooms ALTER COLUMN created_remote_at DROP NOT NULL');
        DB::statement(<<<'SQL'
            UPDATE classrooms
            SET status = CASE
                WHEN ended_at IS NOT NULL THEN 'ended'
                WHEN started_at IS NOT NULL THEN 'running'
                ELSE 'provisioned'
            END,
            provision_attempts = 1
        SQL);
    }

    public function down(): void
    {
        DB::table('classrooms')->whereNull('external_id')->delete();
        DB::statement('ALTER TABLE classrooms ALTER COLUMN external_id SET NOT NULL');
        DB::statement('ALTER TABLE classrooms ALTER COLUMN moderator_secret SET NOT NULL');
        DB::statement('ALTER TABLE classrooms ALTER COLUMN attendee_secret SET NOT NULL');
        DB::statement('ALTER TABLE classrooms ALTER COLUMN created_remote_at SET NOT NULL');

        Schema::table('classrooms', function (Blueprint $table): void {
            $table->dropColumn(['status', 'provision_attempts', 'last_error_at', 'deleted_at']);
        });
    }
};
