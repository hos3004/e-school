<?php

declare(strict_types=1);

namespace Modules\SupportBot\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * بذرة الموديول بالاسم الذي يكتشفه DatabaseSeeder تلقائيًا.
 *
 * المحتوى نفسه في SupportBotContentSeeder، وهي آمنة للتشغيل في الإنتاج: تكتب
 * الصفوف العامة وحدها وبـupdateOrCreate، فلا تكرّر ولا تدهس تحرير الأكاديمية.
 */
final class SupportBotSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SupportBotContentSeeder::class);
    }
}
