<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Messaging\Domain\Models\WhatsappCampaign;

/**
 * سياسة حملات واتساب.
 *
 * الصلاحية المستعملة هي صلاحية إنشاء رسالة صادرة نفسها: الحملة إرسالٌ صادر لا
 * مورد جديد بأدوار جديدة، ومن يملك مراسلة أهل المدرسة يملك مراسلة قائمة أرقام.
 * إضافة صلاحية مستقلة كانت ستعني مصفوفة صلاحيات وseeder وأدوارًا تُضبط يدويًا
 * قبل أن تعمل الشاشة.
 */
final class WhatsappCampaignPolicy
{
    /** @param Authenticatable&object{organization_id: string} $user */
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('notifications.outbox.create');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function view(Authenticatable $user, WhatsappCampaign $campaign): bool
    {
        return $user->can('notifications.outbox.create')
            && $campaign->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function create(Authenticatable $user): bool
    {
        return $user->can('notifications.outbox.create');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function start(Authenticatable $user, WhatsappCampaign $campaign): bool
    {
        return $this->view($user, $campaign);
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function stop(Authenticatable $user, WhatsappCampaign $campaign): bool
    {
        return $this->view($user, $campaign);
    }
}
