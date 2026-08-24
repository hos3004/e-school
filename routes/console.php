<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
| المهام المجدولة على مستوى المنصة. مهام الموديولات تُسجَّل داخل
| ModuleServiceProvider الخاص بكل موديول.
|
| ملاحظة تسليمية: أوامر sessions:dispatch-reminders و
| sessions:finalize-due و recordings:enforce-retention ليست موجودة
| بعد — تُضاف مع أوامر موديولاتها (المهام 03) ويُعاد جدولتها هنا
| عند إنشائها. جدولة أمر غير موجود تفشل كل دورة وتلوث السجل.
*/

// إعادة محاولة الإشعارات الفاشلة
Schedule::command('notifications:retry-failed')->everyFifteenMinutes()->withoutOverlapping();

// توزيع الإشعارات التي حان موعدها إلى عمال قناة الإرسال
Schedule::command('notifications:dispatch-due')->everyMinute()->withoutOverlapping();
