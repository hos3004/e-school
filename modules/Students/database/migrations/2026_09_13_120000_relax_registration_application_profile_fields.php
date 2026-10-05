<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * الإنشاء الإداري السريع يبدأ بالاسم واسم المستخدم وكلمة المرور فقط، ويكمل
 * الطالب باقي بياناته عند أول دخول. القيمة الوهمية أسوأ من الفراغ لأنها
 * تُقرأ كبيانات حقيقية في التقارير، لذلك تُفتح الأعمدة للفراغ بدل حشوها.
 * تسجيل الطالب العام يبقى صارمًا عبر FormRequest الخاص به.
 */
return new class extends Migration
{
    private const COLUMNS = ['date_of_birth', 'gender', 'country_id', 'region_id'];

    public function up(): void
    {
        foreach (self::COLUMNS as $column) {
            DB::statement('ALTER TABLE registration_applications ALTER COLUMN '.$column.' DROP NOT NULL');
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $column) {
            DB::statement('ALTER TABLE registration_applications ALTER COLUMN '.$column.' SET NOT NULL');
        }
    }
};
