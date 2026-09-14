<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * سجل مراحل التذكير المُرسلة لكل حصة.
 *
 * العمود القديم sessions.reminder_sent_at يحتمل مرحلة واحدة فقط، والمطلوب
 * الآن أكثر من تذكير للحصة نفسها (تنبيه مبكر ثم روابط الدخول). المفتاح
 * الفريد (session_id, stage) هو ما يمنع تكرار الإرسال عند تداخل تشغيلتين
 * للمجدول أو عند إعادة تشغيله.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_reminder_dispatches', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('session_id', 26);
            $table->string('stage', 64);
            $table->timestampTz('dispatched_at');
            $table->unsignedInteger('recipient_count')->default(0);

            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('session_id')->references('id')->on('sessions')->cascadeOnDelete();

            $table->unique(['session_id', 'stage'], 'session_reminder_dispatches_unique');
            $table->index(['stage', 'dispatched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_reminder_dispatches');
    }
};
