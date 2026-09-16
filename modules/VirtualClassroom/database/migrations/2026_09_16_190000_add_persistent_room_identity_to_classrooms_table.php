<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * غرفة دائمة واحدة لكل جدول متكرر بدل غرفة جديدة لكل حصة أسبوعية:
 * classrooms.room_identity يحمل معرّف الجدول (Scheduling) حين تنتمي الحصة
 * إليه (مباشرة أو عبر تلافٍ)، وتبقى classrooms.session_id تشير إلى آخر حصة
 * استخدمت الغرفة فقط — لم تعد فريدة لأن غرفة واحدة تخدم حصصًا كثيرة بالتتابع.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table): void {
            $table->string('room_identity', 80)->nullable()->after('session_id');
            $table->unsignedInteger('link_generation')->default(1)->after('room_identity');
            $table->timestampTz('rotated_at')->nullable()->after('link_generation');
            $table->char('rotated_by', 26)->nullable()->after('rotated_at');

            $table->unique('room_identity');
            $table->foreign('rotated_by')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('classrooms', function (Blueprint $table): void {
            $table->dropUnique('classrooms_session_id_unique');
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table): void {
            $table->dropIndex(['session_id']);
            $table->unique('session_id');
        });

        Schema::table('classrooms', function (Blueprint $table): void {
            $table->dropForeign(['rotated_by']);
            $table->dropUnique(['room_identity']);
            $table->dropColumn(['room_identity', 'link_generation', 'rotated_at', 'rotated_by']);
        });
    }
};
