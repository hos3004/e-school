<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postponement_requests', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('session_id', 26);
            $table->char('requested_by', 26);
            $table->char('requested_for_student_id', 26);
            $table->string('status');
            $table->timestampTz('proposed_start');
            $table->timestampTz('proposed_by_teacher_start')->nullable();
            $table->timestampTz('agreed_start')->nullable();
            $table->char('makeup_session_id', 26)->nullable();
            $table->text('reason');
            $table->text('teacher_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->char('responded_by', 26)->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->foreign('session_id')->references('id')->on('sessions')->restrictOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('requested_for_student_id')->references('id')->on('student_profiles')->restrictOnDelete();
            $table->foreign('makeup_session_id')->references('id')->on('sessions')->restrictOnDelete();
            $table->foreign('responded_by')->references('id')->on('users')->restrictOnDelete();

            $table->index('session_id');
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postponement_requests');
    }
};
