<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_change_requests', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('schedule_id', 26);
            $table->char('staff_profile_id', 26);
            $table->char('requested_by', 26);
            $table->string('status');
            $table->jsonb('proposed_weekdays');
            $table->jsonb('proposed_weekly_slots')->nullable();
            $table->time('proposed_start_time');
            $table->unsignedSmallInteger('proposed_interval_weeks')->default(1);
            $table->text('reason');
            $table->timestampTz('expires_at');
            $table->char('decided_by', 26)->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampTz('applied_at')->nullable();
            $table->timestampsTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('schedule_id')->references('id')->on('schedules')->cascadeOnDelete();
            $table->foreign('staff_profile_id')->references('id')->on('staff_profiles')->restrictOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('decided_by')->references('id')->on('users')->restrictOnDelete();

            $table->index(['organization_id', 'schedule_id']);
            $table->index(['organization_id', 'staff_profile_id']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('schedule_change_approvals', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('schedule_change_request_id', 26);
            $table->char('student_profile_id', 26);
            $table->char('student_user_id', 26)->nullable();
            $table->string('status');
            $table->char('responded_by', 26)->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('schedule_change_request_id', 'schedule_change_approvals_request_id_foreign')
                ->references('id')->on('schedule_change_requests')->cascadeOnDelete();
            $table->foreign('student_profile_id')->references('id')->on('student_profiles')->restrictOnDelete();
            $table->foreign('student_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('responded_by')->references('id')->on('users')->restrictOnDelete();

            $table->unique(
                ['schedule_change_request_id', 'student_profile_id'],
                'schedule_change_approvals_request_student_unique',
            );
            $table->index(['organization_id', 'student_profile_id', 'status'], 'schedule_change_approvals_student_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_change_approvals');
        Schema::dropIfExists('schedule_change_requests');
    }
};
