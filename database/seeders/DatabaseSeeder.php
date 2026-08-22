<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Shared\Module\ModuleRegistry;

/**
 * نقطة الدخول للتعبئة.
 *
 * لا تُسمّى بذور الموديولات يدويًا هنا — تُكتشف تلقائيًا من سجل الموديولات
 * المفعّلة بترتيب التحميل نفسه، حتى تُنشأ الجداول المرجعية قبل من يعتمد عليها.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ModuleRegistry::enabled() as $module) {
            $seeder = ModuleRegistry::namespace($module)
                .chr(92).'Database'
                .chr(92).'Seeders'
                .chr(92).$module.'Seeder';

            if (! class_exists($seeder)) {
                continue;
            }

            $this->command?->info("Seeding: {$module}");
            $this->call($seeder);
        }
    }
}
