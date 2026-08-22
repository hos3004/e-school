<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
| المهام المجدولة على مستوى المنصة. مهام الموديولات تُسجَّل داخل
| ModuleServiceProvider الخاص بكل موديول.
*/

// حذف التسجيلات بعد انتهاء مدة الاحتفاظ (config/recordings.php)
Schedule::command('recordings:enforce-retention')->dailyAt('03:17');

// تصفير عدّادات الغياب الشهرية (config/discipline.php)
Schedule::command('discipline:reset-counters')->monthlyOn(1, '00:07');

// إعادة محاولة الإشعارات الفاشلة
Schedule::command('notifications:retry-failed')->everyFifteenMinutes()->withoutOverlapping();

// توزيع الإشعارات التي حان موعدها إلى عمال قناة الإرسال
Schedule::command('notifications:dispatch-due')->everyMinute()->withoutOverlapping();

// تذكير قبل الحصص
Schedule::command('sessions:dispatch-reminders')->everyFiveMinutes();

// إغلاق الحصص المنتهية وإنشاء قيود الرواتب
Schedule::command('sessions:finalize-due')->everyTenMinutes();
