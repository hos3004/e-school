<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * كود عرض قصير لملف الوصي، بنفس نمط student_code وstaff_code (E001 · T001).
 *
 * الجدول فارغ وقت هذه الهجرة (تحقّق 2026-09-18)، فالعمود يُضاف NOT NULL UNIQUE
 * مباشرة دون حاجة لتعبئة قيم قديمة أولًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardian_profiles', function (Blueprint $table): void {
            $table->string('guardian_code')->unique()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('guardian_profiles', function (Blueprint $table): void {
            $table->dropColumn('guardian_code');
        });
    }
};
