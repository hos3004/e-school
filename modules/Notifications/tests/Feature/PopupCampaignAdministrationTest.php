<?php

declare(strict_types=1);

namespace Modules\Notifications\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Identity\Domain\Models\User;
use Modules\Notifications\Application\Actions\SavePopupCampaignAction;
use Modules\Notifications\Domain\Enums\PopupCampaignStatus;
use Modules\Notifications\Domain\Enums\PopupFrequency;
use Modules\Notifications\Domain\Enums\PopupPlacement;
use Modules\Notifications\Domain\Enums\PopupType;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Modules\Notifications\Presentation\Filament\Resources\PopupCampaignResource;
use Modules\Notifications\Presentation\Filament\Resources\PopupCampaignResource\Pages\CreatePopupCampaign;
use Modules\Organization\Domain\Models\Organization;
use Shared\Support\BusinessRuleViolation;
use Tests\TestCase;

final class PopupCampaignAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_create_page_renders_and_saves_a_tenant_scoped_draft(): void
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->inOrganization((string) $organization->id)->create();
        $this->allowPopupAdministrationFor($actor);
        $this->actingAs($actor);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get(PopupCampaignResource::getUrl('create', panel: 'admin'))->assertOk();

        Livewire::test(CreatePopupCampaign::class)
            ->fillForm($this->validFormData())
            ->call('create')
            ->assertHasNoFormErrors();

        $campaign = PopupCampaign::query()->sole();

        self::assertSame((string) $organization->id, (string) $campaign->organization_id);
        self::assertSame(PopupCampaignStatus::Draft, $campaign->status);
        self::assertNull($campaign->action_type);
        self::assertNull($campaign->action_target);
        self::assertTrue(DB::table('audit_log')->where([
            'action' => 'notifications.popup_campaign_created',
            'auditable_id' => (string) $campaign->getKey(),
            'reason' => 'إطلاق تنويه الفصل الجديد',
        ])->exists());
    }

    public function test_create_form_reports_validation_errors_instead_of_saving_invalid_content(): void
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->inOrganization((string) $organization->id)->create();
        $this->allowPopupAdministrationFor($actor);
        $this->actingAs($actor);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreatePopupCampaign::class)
            ->fillForm(array_replace($this->validFormData(), [
                'title' => ['ar' => ''],
                'audiences' => [],
                'ends_at' => now('UTC')->subDay()->format('Y-m-d H:i:s'),
            ]))
            ->call('create')
            ->assertHasFormErrors(['title.ar', 'audiences', 'ends_at']);

        self::assertSame(0, PopupCampaign::query()->count());
    }

    public function test_create_policy_requires_the_explicit_permission(): void
    {
        $organization = Organization::factory()->create();
        $allowed = User::factory()->inOrganization((string) $organization->id)->create();
        $denied = User::factory()->inOrganization((string) $organization->id)->create();
        $this->allowPopupAdministrationFor($allowed);

        self::assertTrue(Gate::forUser($allowed)->allows('create', PopupCampaign::class));
        self::assertFalse(Gate::forUser($denied)->allows('create', PopupCampaign::class));
    }

    public function test_save_action_rejects_a_foreign_tenant_record(): void
    {
        $local = Organization::factory()->create();
        $foreign = Organization::factory()->create();
        $actor = User::factory()->inOrganization((string) $local->id)->create();
        $campaign = $this->campaign($foreign);

        try {
            app(SavePopupCampaignAction::class)->execute(
                campaign: $campaign,
                organizationId: (string) $local->id,
                attributes: [],
                scheduleChanges: null,
                actorId: (string) $actor->id,
                reason: 'محاولة تعديل حملة أجنبية',
            );
            self::fail('Expected a foreign-tenant rejection.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('notifications.popup_foreign_tenant', $violation->rule);
        }

        self::assertSame('foreign-campaign', $campaign->refresh()->internal_name);
    }

    /** @return array<string, mixed> */
    private function validFormData(): array
    {
        return [
            'internal_name' => 'new-term-notice',
            'type' => PopupType::General->value,
            'title' => ['ar' => 'بداية الفصل الجديد', 'en' => 'New term'],
            'body' => ['ar' => 'يرجى مراجعة الجدول الجديد.', 'en' => 'Please review the new schedule.'],
            'audiences' => ['student'],
            'placement' => PopupPlacement::AfterLogin->value,
            'page_key' => null,
            'frequency' => PopupFrequency::Once->value,
            'is_dismissible' => true,
            'requires_acknowledgement' => false,
            'priority' => 5,
            'starts_at' => now('UTC')->addMinute()->format('Y-m-d H:i:s'),
            'ends_at' => now('UTC')->addWeek()->format('Y-m-d H:i:s'),
            'action_type' => '',
            'internal_action_target' => null,
            'external_action_target' => null,
            'reason' => 'إطلاق تنويه الفصل الجديد',
        ];
    }

    private function campaign(Organization $organization): PopupCampaign
    {
        return PopupCampaign::query()->create([
            'organization_id' => (string) $organization->id,
            'internal_name' => 'foreign-campaign',
            'type' => PopupType::General,
            'status' => PopupCampaignStatus::Draft,
            'priority' => 5,
            'title' => ['ar' => 'حملة أجنبية'],
            'body' => ['ar' => 'محتوى الحملة الأجنبية'],
            'audiences' => ['student'],
            'placement' => PopupPlacement::AfterLogin,
            'frequency' => PopupFrequency::Once,
            'is_dismissible' => true,
            'requires_acknowledgement' => false,
            'starts_at' => now('UTC'),
        ]);
    }

    private function allowPopupAdministrationFor(User $actor): void
    {
        $this->seed(AccessControlSeeder::class);

        foreach (['admin.panel.access', 'popup_campaign.view_any', 'popup_campaign.create'] as $permissionName) {
            $permission = Permission::query()->where('name', $permissionName)->firstOrFail();
            ModelHasPermission::query()->create([
                'permission_id' => (string) $permission->getKey(),
                'model_type' => $actor->getMorphClass(),
                'model_id' => (string) $actor->getAuthIdentifier(),
            ]);
        }

        app(PermissionGateRegistrar::class)->register();
    }
}
