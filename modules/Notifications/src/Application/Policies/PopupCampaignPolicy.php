<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Notifications\Domain\Models\PopupCampaign;

/**
 * Popup campaign abilities use explicit permissions and tenant ownership.
 * Publishing, editing, and analytics remain separate abilities; hard delete is forbidden.
 */
final class PopupCampaignPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('popup_campaign.view_any');
    }

    public function view(Authenticatable $user, PopupCampaign $campaign): bool
    {
        return $this->belongsToActorOrganization($user, $campaign)
            && $user->can('popup_campaign.view');
    }

    public function create(Authenticatable $user): bool
    {
        return $user->can('popup_campaign.create');
    }

    public function update(Authenticatable $user, PopupCampaign $campaign): bool
    {
        return $this->belongsToActorOrganization($user, $campaign)
            && $user->can('popup_campaign.update');
    }

    public function publish(Authenticatable $user, PopupCampaign $campaign): bool
    {
        return $this->belongsToActorOrganization($user, $campaign)
            && $user->can('popup_campaign.publish');
    }

    public function pause(Authenticatable $user, PopupCampaign $campaign): bool
    {
        return $this->belongsToActorOrganization($user, $campaign)
            && $user->can('popup_campaign.pause');
    }

    public function archive(Authenticatable $user, PopupCampaign $campaign): bool
    {
        return $this->belongsToActorOrganization($user, $campaign)
            && $user->can('popup_campaign.archive');
    }

    public function viewAnalytics(Authenticatable $user, PopupCampaign $campaign): bool
    {
        return $this->belongsToActorOrganization($user, $campaign)
            && $user->can('popup_campaign.view_analytics');
    }

    public function delete(Authenticatable $user): bool
    {
        // Project policy requires archival instead of hard deletion.
        return false;
    }

    private function belongsToActorOrganization(
        Authenticatable $user,
        PopupCampaign $campaign,
    ): bool {
        $organizationId = data_get($user, 'organization_id');

        return is_string($organizationId) && $organizationId !== ''
            && hash_equals($organizationId, (string) $campaign->organization_id);
    }
}
