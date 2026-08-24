<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Policies;

use Modules\Notifications\Domain\Models\NotificationTemplate;

/**
 * سياسة قوالب الإشعارات — إدارة النصوص من لوحة الأدمن دون فحص أسماء أدوار.
 *
 * البوابة الوحيدة settings.manage (نفس بوابة إدارة الإشعارات في المصفوفة).
 * القالب العام (organization_id = null) مرجع مشترك: يُقرأ ولا يُعدَّل من مؤسسة
 * واحدة؛ التخصيص يكون بإنشاء نسخة override خاصة بالمؤسسة تتفوق عليه في العرض.
 */
final class NotificationTemplatePolicy
{
    public function viewAny(mixed $user): bool
    {
        return $user->can('settings.manage');
    }

    public function view(mixed $user, NotificationTemplate $template): bool
    {
        return $user->can('settings.manage')
            && $this->visibleToOrganization($user, $template);
    }

    public function create(mixed $user): bool
    {
        return $user->can('settings.manage');
    }

    public function update(mixed $user, NotificationTemplate $template): bool
    {
        return $user->can('settings.manage')
            && $this->ownsTemplate($user, $template);
    }

    public function delete(mixed $user, NotificationTemplate $template): bool
    {
        return $user->can('settings.manage')
            && $this->ownsTemplate($user, $template);
    }

    /**
     * القالب العام يُقرأ من كل مؤسسة؛ القالب الخاص يُقرأ من مؤسسته فقط.
     */
    private function visibleToOrganization(mixed $user, NotificationTemplate $template): bool
    {
        return $template->organization_id === null
            || $template->organization_id === $user->organization_id;
    }

    /**
     * التعديل والحذف على قوالب المؤسسة فقط — لا مساس بالقالب العام المشترك.
     */
    private function ownsTemplate(mixed $user, NotificationTemplate $template): bool
    {
        return $template->organization_id !== null
            && $template->organization_id === $user->organization_id;
    }
}
