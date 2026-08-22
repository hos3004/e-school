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
        Schema::create('registration_applications', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('user_id', 26)->nullable();
            $table->char('student_profile_id', 26)->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->string('full_name');
            $table->date('date_of_birth');
            $table->string('gender', 16);
            $table->char('country_id', 26);
            $table->char('region_id', 26);
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->char('preferred_program_id', 26)->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->char('reviewed_by', 26)->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->char('duplicate_of_application_id', 26)->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'email']);
            $table->index(['organization_id', 'phone']);
            $table->index(['country_id', 'region_id']);

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('student_profile_id')->references('id')->on('student_profiles')->restrictOnDelete();
            $table->foreign('country_id')->references('id')->on('countries')->restrictOnDelete();
            $table->foreign('region_id')->references('id')->on('regions')->restrictOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->restrictOnDelete();
        });

        // PostgreSQL يحتاج اكتمال الـPRIMARY KEY قبل إضافة المرجع الذاتي.
        Schema::table('registration_applications', function (Blueprint $table): void {
            $table->foreign('duplicate_of_application_id')
                ->references('id')
                ->on('registration_applications')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE registration_applications
            ADD CONSTRAINT registration_applications_status_check
            CHECK (status IN ('draft', 'submitted', 'under_review', 'accepted', 'rejected', 'waiting_assignment', 'assigned'))
            SQL);

        // لا يمكن أن يصبح الطلب صالحًا للتوزيع بلا ملف طالب أنشأه مسار القبول.
        DB::statement(<<<'SQL'
            ALTER TABLE registration_applications
            ADD CONSTRAINT registration_applications_assignment_clearance_check
            CHECK (
                (status IN ('waiting_assignment', 'assigned') AND student_profile_id IS NOT NULL)
                OR
                (status IN ('draft', 'submitted', 'under_review', 'accepted', 'rejected') AND student_profile_id IS NULL)
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_applications');
    }
};
