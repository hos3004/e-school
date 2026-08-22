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
        Schema::table('programs', function (Blueprint $table): void {
            $table->string('program_type', 32)->default('ongoing')->index();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('target_gender', 16)->default('all');
            $table->smallInteger('age_from')->nullable();
            $table->smallInteger('age_to')->nullable();
            $table->jsonb('objectives')->nullable();
            $table->string('language', 16)->nullable();
        });

        // PostgreSQL CHECK constraint for program duration consistency
        DB::statement("
            ALTER TABLE programs ADD CONSTRAINT chk_program_duration CHECK (
                (program_type = 'fixed_duration' AND start_date IS NOT NULL AND end_date IS NOT NULL)
                OR
                (program_type = 'ongoing' AND end_date IS NULL)
            )
        ");

        // Pricing note: fixed_duration uses single fixed amount, ongoing uses monthly subscription/per-session fee.

        Schema::table('courses', function (Blueprint $table): void {
            $table->string('session_mode', 32)->default('both');
            $table->smallInteger('age_from')->nullable();
            $table->smallInteger('age_to')->nullable();
            $table->string('target_gender', 16)->nullable();
            $table->integer('default_duration_minutes')->nullable();
            $table->integer('sessions_per_week')->nullable();
            $table->jsonb('prerequisites')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn([
                'session_mode',
                'age_from',
                'age_to',
                'target_gender',
                'default_duration_minutes',
                'sessions_per_week',
                'prerequisites',
            ]);
        });

        DB::statement('ALTER TABLE programs DROP CONSTRAINT IF EXISTS chk_program_duration');

        Schema::table('programs', function (Blueprint $table): void {
            $table->dropColumn([
                'program_type',
                'start_date',
                'end_date',
                'target_gender',
                'age_from',
                'age_to',
                'objectives',
                'language',
            ]);
        });
    }
};
