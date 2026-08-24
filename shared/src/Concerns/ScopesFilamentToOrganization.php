<?php

declare(strict_types=1);

namespace Shared\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * حصر مورد Filament على مؤسسة المستخدم الحالي.
 *
 * عزل المؤسسات ضابط أمني إلزامي في `docs/phase-1-approved-scope.md` §4.2 حتى
 * مع مؤسسة واحدة تعمل اليوم — لأن الكود نفسه مرشَّح للنشر، وإضافة مؤسسة ثانية
 * لاحقًا لا يجوز أن تكشف بيانات الأولى بأثر رجعي.
 *
 * المستخدم بلا مؤسسة يرى **صفرًا** لا كل شيء: `whereRaw('1 = 0')` بدل تجاهل
 * الشرط. الفرق جوهري — التجاهل يحوّل خطأ بيانات إلى تسريب.
 *
 * الشرط الوحيد للاستعمال: أن يعرّف النموذج `scopeForOrganization`.
 */
trait ScopesFilamentToOrganization
{
    /**
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $organizationId = data_get(auth()->user(), 'organization_id');

        if (!is_string($organizationId) || $organizationId === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query->forOrganization($organizationId);
    }
}
