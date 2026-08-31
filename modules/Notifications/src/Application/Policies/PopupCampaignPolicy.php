<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * صلاحيات حملات النوافذ المنبثقة — عبر أسماء صلاحيات فقط، لا فحص أدوار.
 * النشر منفصل عن التحرير، والتحليلات بصلاحية مستقلة. لا حذف نهائي إطلاقًا.
 */
final class PopupCampaignPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('popup_campaign.view_any');
    }

    public function view(Authenticatable $user): bool
    {
        return $user->can('popup_campaign.view');
    }

    public function create(Authenticatable $user): bool
    {
        return $user->can('popup_campaign.create');
    }

    public function update(Authenticatable $user): bool
    {
        return $user->can('popup_campaign.update');
    }

    public function publish(Authenticatable $user): bool
    {
        return $user->can('popup_campaign.publish');
    }

    public function pause(Authenticatable $user): bool
    {
        return $user->can('popup_campaign.pause');
    }

    public function archive(Authenticatable $user): bool
    {
        return $user->can('popup_campaign.archive');
    }

    public function viewAnalytics(Authenticatable $user): bool
    {
        return $user->can('popup_campaign.view_analytics');
    }

    public function delete(Authenticatable $user): bool
    {
        // الأرشفة بديل الحذف — سياسة المشروع.
        return false;
    }
}
