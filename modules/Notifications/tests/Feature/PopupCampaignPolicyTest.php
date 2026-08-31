<?php

declare(strict_types=1);

namespace Modules\Notifications\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Identity\Domain\Models\User;
use Modules\Notifications\Domain\Enums\PopupCampaignStatus;
use Modules\Notifications\Domain\Enums\PopupFrequency;
use Modules\Notifications\Domain\Enums\PopupPlacement;
use Modules\Notifications\Domain\Enums\PopupType;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Modules\Organization\Domain\Models\Organization;
use Tests\TestCase;

final class PopupCampaignPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_abilities_require_permission_and_same_organization(): void
    {
        $this->seed(AccessControlSeeder::class);

        $organization = Organization::factory()->create();
        $foreignOrganization = Organization::factory()->create();
        $actor = User::factory()->inOrganization((string) $organization->id)->create();
        $denied = User::factory()->inOrganization((string) $organization->id)->create();
        $local = $this->campaign($organization);
        $foreign = $this->campaign($foreignOrganization);

        $permissions = [
            'view' => 'popup_campaign.view',
            'update' => 'popup_campaign.update',
            'publish' => 'popup_campaign.publish',
            'pause' => 'popup_campaign.pause',
            'archive' => 'popup_campaign.archive',
            'viewAnalytics' => 'popup_campaign.view_analytics',
        ];

        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->where('name', $permissionName)->firstOrFail();
            ModelHasPermission::query()->create([
                'permission_id' => (string) $permission->getKey(),
                'model_type' => $actor->getMorphClass(),
                'model_id' => (string) $actor->getAuthIdentifier(),
            ]);
        }
        app(PermissionGateRegistrar::class)->register();

        foreach (array_keys($permissions) as $ability) {
            self::assertTrue(Gate::forUser($actor)->allows($ability, $local), $ability.' should allow local campaign');
            self::assertFalse(Gate::forUser($actor)->allows($ability, $foreign), $ability.' should deny foreign campaign');
            self::assertFalse(Gate::forUser($denied)->allows($ability, $local), $ability.' should require permission');
        }
    }

    private function campaign(Organization $organization): PopupCampaign
    {
        return PopupCampaign::query()->create([
            'organization_id' => (string) $organization->id,
            'internal_name' => 'campaign-'.str()->ulid(),
            'type' => PopupType::General,
            'status' => PopupCampaignStatus::Draft,
            'priority' => 5,
            'title' => ['ar' => 'Test'],
            'body' => ['ar' => 'Body'],
            'audiences' => ['all_authenticated'],
            'placement' => PopupPlacement::AfterLogin,
            'frequency' => PopupFrequency::Once,
            'is_dismissible' => true,
            'requires_acknowledgement' => false,
            'starts_at' => now('UTC'),
        ]);
    }
}
