<?php

declare(strict_types=1);

namespace Modules\Groups\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Presentation\Filament\Resources\GroupResource;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Tests\TestCase;

final class GroupAdminHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_create_and_operations_hub_render_without_manual_organization_input(): void
    {
        Gate::before(static fn (): bool => true);
        Filament::setCurrentPanel('admin');

        $organization = Organization::factory()->create();
        $operator = User::factory()->inOrganization((string) $organization->id)->create();
        $group = Group::factory()->create([
            'organization_id' => (string) $organization->id,
            'code' => 'GR-HUB-001',
            'name' => ['ar' => 'مجموعة التجربة', 'en' => 'Demo Group'],
        ]);

        $this->actingAs($operator)
            ->get(GroupResource::getUrl('create', panel: 'admin'))
            ->assertOk()
            ->assertSeeText(__('groups::filament.fields.name_ar'))
            ->assertDontSee('data.organization_id', false);

        $this->get(GroupResource::getUrl('view', ['record' => $group], panel: 'admin'))
            ->assertOk()
            ->assertSeeText('GR-HUB-001')
            ->assertSeeText(__('groups::filament.hub.programs'))
            ->assertSeeText(__('groups::filament.hub.teachers'))
            ->assertSeeText(__('groups::filament.hub.students'))
            ->assertSeeText(__('groups::filament.hub.sessions'));
    }
}
