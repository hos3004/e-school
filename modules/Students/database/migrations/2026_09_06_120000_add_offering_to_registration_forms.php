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
        Schema::table('registration_forms', function (Blueprint $table): void {
            // Academic identifiers are validated through RegistrationOfferingQueries.
            $table->char('preferred_program_id', 26)->nullable();
            $table->char('preferred_course_id', 26)->nullable();
            $table->index(['organization_id', 'preferred_course_id'], 'reg_forms_org_course_idx');
        });
        DB::statement('ALTER TABLE registration_forms ADD CONSTRAINT reg_forms_offering_pair_check CHECK ((preferred_program_id IS NULL) = (preferred_course_id IS NULL))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE registration_forms DROP CONSTRAINT reg_forms_offering_pair_check');
        Schema::table('registration_forms', function (Blueprint $table): void {
            $table->dropIndex('reg_forms_org_course_idx');
            $table->dropColumn(['preferred_program_id', 'preferred_course_id']);
        });
    }
};
