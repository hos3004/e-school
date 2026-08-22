<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reactivation_requests', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('enrollment_id', 26);
            $table->char('requested_by', 26);
            $table->string('status', 32);
            $table->integer('attempt_number');
            $table->char('assessment_attempt_id', 26)->nullable();
            $table->text('student_statement');
            $table->char('reviewer_id', 26)->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestampsTz();

            $table->index('organization_id', 'reactivation_requests_organization_id_index');
            $table->index('enrollment_id', 'reactivation_requests_enrollment_id_index');
            $table->index('requested_by', 'reactivation_requests_requested_by_index');
            $table->index('status', 'reactivation_requests_status_index');
            $table->index('assessment_attempt_id', 'reactivation_requests_assessment_attempt_index');
            $table->index('reviewer_id', 'reactivation_requests_reviewer_id_index');

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->restrictOnDelete();
            $table->foreign('requested_by')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
            $table->foreign('reviewer_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reactivation_requests');
    }
};
