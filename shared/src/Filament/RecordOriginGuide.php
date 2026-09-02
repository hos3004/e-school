<?php

declare(strict_types=1);

namespace Shared\Filament;

use Filament\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Route;

/**
 * يشرح للمستخدم من أين تأتي سجلات صفحةٍ لا تُنشأ سجلاتها من اللوحة.
 *
 * أحد وعشرون موردًا يُرجع `canCreate(): false` لأن مصدر سجلاته خارج اللوحة
 * بحكم المعمارية — الحضور من الحصة، وإجراءات الانضباط من محرّك التصعيد، وقيود
 * المستحقات من اعتماد الحصة. هذا صحيح معماريًا، لكن الواجهة لم تكن تقوله:
 * جدول فارغ بلا زر وبلا كلمة. و`emptyStateHeading` كان مستخدمًا في **ملف واحد**
 * بالمستودع كله.
 *
 * الفرق بين «لا يوجد شيء» و«لا يوجد شيء بعد، وهذا مكان مجيئه» هو الفرق بين
 * مستخدم يظن النظام معطّلًا ومستخدم يعرف خطوته التالية.
 *
 * الوجهة تُمرَّر **باسم مسار** لا بصنف مورد: استيراد مورد موديولٍ آخر من
 * موديول يكسر حدود التغليف في البند 2 من عقد المستودع.
 */
final class RecordOriginGuide
{
    /**
     * @param string $translationPrefix مثل `attendance::origin` — يقرأ منه
     *                                  `.heading` و`.description` و`.action`
     * @param ?string $routeName مسار المصدر، أو null حين لا وجهة في اللوحة
     */
    public static function for(
        Table $table,
        string $translationPrefix,
        string $icon = 'heroicon-o-inbox',
        ?string $routeName = null,
    ): Table {
        $table = $table
            ->emptyStateIcon($icon)
            ->emptyStateHeading(__($translationPrefix.'.heading'))
            ->emptyStateDescription(__($translationPrefix.'.description'));

        $action = self::originAction($translationPrefix, $icon, $routeName);

        if (!$action instanceof Action) {
            return $table;
        }

        // الزر في الحالتين: في الفراغ ليدل على المصدر، وفي الرأس ليبقى الطريق
        // ظاهرًا بعد امتلاء الجدول أيضًا.
        return $table
            ->emptyStateActions([$action])
            ->headerActions([$action]);
    }

    private static function originAction(
        string $translationPrefix,
        string $icon,
        ?string $routeName,
    ): ?Action {
        if ($routeName === null) {
            return null;
        }

        /*
         * المسار قد يغيب: مورد معطّل بمفتاح ميزة لا يسجّل مساراته. زرٌّ يشير إلى
         * مسار غير مسجَّل يرمي RouteNotFoundException ويُسقط الصفحة كاملة، وهو
         * ثمن باهظ لعنصر إرشادي.
         */
        if (!Route::has($routeName)) {
            return null;
        }

        return Action::make('record_origin')
            ->label(__($translationPrefix.'.action'))
            ->icon($icon)
            ->color('gray')
            ->url(route($routeName));
    }
}
