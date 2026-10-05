<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * السماح ببدء مرن داخل يوم الحصة يُختار لكل جدول على حدة — لا يُفعَّل تلقائيًا
 * لكل الحصص الفردية. الإدارة تحدد أي علاقة معلم/طالب تحتاج هذه المرونة
 * بالتراضي، وتتركها معطّلة لباقي الجداول التي تسير على النظام الصارم كالمعتاد.
 *
 * قيد على مستوى القاعدة لا الكود فقط: الحصة الجماعية لا تحمل هذا الاختيار
 * أبدًا. Create/UpdateScheduleAction يفرضان false للجماعي، لكن أي مسار كتابة
 * مستقبلي يتجاوزهما (سكربت إصلاح بيانات، أمر artisan، تعديل مباشر) لن يجد
 * حارسًا في التطبيق — هذا القيد يمنعه عند القاعدة نفسها، مطابقًا لقيد
 * schedules_target_check الموجود على نفس الجدول.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            $table->boolean('flexible_start')->default(false)->after('session_type');
        });

        DB::statement('ALTER TABLE schedules DROP CONSTRAINT IF EXISTS schedules_flexible_start_individual_only_check');
        DB::statement(<<<'SQL'
            ALTER TABLE schedules
            ADD CONSTRAINT schedules_flexible_start_individual_only_check
            CHECK (group_id IS NULL OR flexible_start = false)
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE schedules DROP CONSTRAINT IF EXISTS schedules_flexible_start_individual_only_check');

        Schema::table('schedules', function (Blueprint $table): void {
            $table->dropColumn('flexible_start');
        });
    }
};
