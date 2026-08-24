<?php

declare(strict_types=1);

namespace Shared\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * عزل مورد Filament لا يحمل جدوله `organization_id` بنفسه.
 *
 * الجداول التابعة (مستوى داخل برنامج · محاولة داخل اختبار · مشارك داخل حصة)
 * ترث انتماءها من أبيها. القيد يُطبَّق عبر العلاقة المعلنة على النموذج نفسه،
 * فلا يعرف المورد جدول موديول آخر ولا يستورد نموذجه.
 *
 * الصنف المستعمِل يحدّد اسم العلاقة عبر `organizationRelation()`.
 */
trait ScopesFilamentToOrganizationVia
{
    /**
     * اسم العلاقة التي تصل إلى نموذج يحمل `organization_id`.
     */
    abstract protected static function organizationRelation(): string;

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

        return $query->whereHas(
            static::organizationRelation(),
            static fn (Builder $parent): Builder => $parent->where('organization_id', $organizationId),
        );
    }
}
