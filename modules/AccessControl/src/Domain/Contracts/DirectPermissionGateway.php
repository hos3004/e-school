<?php

declare(strict_types=1);

namespace Modules\AccessControl\Domain\Contracts;

/**
 * منفذ منح وسحب صلاحية مباشرة لحساب، دون تسريب نماذج AccessControl.
 *
 * القناة المعلنة للموديولات الأخرى (شاشة المستخدم في Identity مثلًا) لتبديل
 * الصلاحيات الاختيارية في config('accesscontrol.optional_direct_permissions').
 *
 * السبب إلزامي في الطرفين لأن تغيير الصلاحيات يُسجَّل في التدقيق دائمًا.
 */
interface DirectPermissionGateway
{
    /** @return bool false إذا كانت ممنوحة مسبقًا فلا يتكرر المنح */
    public function grantIfMissing(
        string $permissionName,
        string $modelType,
        string $modelId,
        string $organizationId,
        string $actorId,
        string $reason,
    ): bool;

    /** @return bool false إذا لم تكن ممنوحة أصلًا فلا يُسحب شيء */
    public function revokeIfPresent(
        string $permissionName,
        string $modelType,
        string $modelId,
        string $organizationId,
        string $actorId,
        string $reason,
    ): bool;
}
