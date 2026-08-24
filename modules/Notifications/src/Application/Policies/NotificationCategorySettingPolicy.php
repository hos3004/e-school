<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Policies;

use Modules\Notifications\Domain\Models\NotificationCategorySetting;

/**
 * سياسة إعدادات فئات الإشعارات — تعديل توجيه الفئات من اللوحة على بوابة
 * settings.manage، ودائمًا ضمن مؤسسة المستخدم. الفئات ثابتة (معرّفة في config)
 * فلا إنشاء ولا حذف يدوي؛ الصفوف تُضمَن تلقائيًا عند فتح الشاشة.
 */
final class NotificationCategorySettingPolicy
{
    public function viewAny(mixed $user): bool
    {
        return $user->can('settings.manage');
    }

    public function view(mixed $user, NotificationCategorySetting $setting): bool
    {
        return $user->can('settings.manage')
            && $setting->organization_id === $user->organization_id;
    }

    public function update(mixed $user, NotificationCategorySetting $setting): bool
    {
        return $user->can('settings.manage')
            && $setting->organization_id === $user->organization_id;
    }

    public function create(mixed $user): bool
    {
        return false;
    }

    public function delete(mixed $user, NotificationCategorySetting $setting): bool
    {
        return false;
    }
}
