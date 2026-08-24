<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * حصر أرقام لوحة التحكم على مؤسسة المستخدم الحالي.
 *
 * ودجات اللوحة تعيش خارج الموديولات وتقرأ بأسماء الجداول مباشرة، فلا تسري
 * عليها عوازل موارد Filament. كانت كل البطاقات والعدّادات تجمع عبر المؤسسات
 * جميعًا — أي أن رقمًا واحدًا معروضًا للمشرف قد يخلط بيانات مؤسسته ببيانات
 * غيرها. عزل المؤسسات ضابط أمني إلزامي في `docs/phase-1-approved-scope.md` §4.2.
 */
trait ScopesToOrganization
{
    protected function organizationId(): ?string
    {
        $organizationId = data_get(auth()->user(), 'organization_id');

        return is_string($organizationId) && $organizationId !== ''
            ? $organizationId
            : null;
    }

    /**
     * استعلام على جدول يحمل `organization_id` مباشرة.
     *
     * غياب المؤسسة يعني صفرًا لا كل شيء.
     */
    protected function scoped(string $table): Builder
    {
        $query = DB::table($table);
        $organizationId = $this->organizationId();

        return $organizationId === null
            ? $query->whereRaw('1 = 0')
            : $query->where($table.'.organization_id', $organizationId);
    }

    /**
     * استعلام على جدول لا يحمل `organization_id`، فيُقيَّد عبر جدول وسيط يحمله.
     *
     * مثال: `postponement_requests` تُقيَّد عبر `sessions`.
     */
    protected function scopedVia(
        string $table,
        string $ownerTable,
        string $foreignKey,
        string $ownerKey = 'id',
    ): Builder {
        $query = DB::table($table)
            ->join($ownerTable, $ownerTable.'.'.$ownerKey, '=', $table.'.'.$foreignKey);

        $organizationId = $this->organizationId();

        return $organizationId === null
            ? $query->whereRaw('1 = 0')
            : $query->where($ownerTable.'.organization_id', $organizationId);
    }
}
