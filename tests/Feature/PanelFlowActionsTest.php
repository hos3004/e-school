<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Certificates\Domain\Enums\BadgeTier;
use Modules\Certificates\Domain\Models\Badge;
use Modules\Certificates\Domain\Models\BadgeAward;
use Modules\Certificates\Presentation\Filament\Resources\BadgeFilamentResource;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Presentation\Filament\Resources\GroupResource;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Tests\TestCase;

/**
 * زرّان يغلقان انقطاعًا في التسلسل ومنحًا معطّلًا.
 *
 * «جدولة حصص» من صفحة المجموعة: كان المنسّق يُسند المعلم ويضع الطلاب ثم يقف،
 * فلا شيء يقود إلى تحديد المواعيد. و«منح الشارة»: `AwardBadgeAction` كان بلا
 * زر فلا منح إلا بقاعدة آلية.
 */
final class PanelFlowActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_an_active_group_offers_a_link_that_preselects_it_for_scheduling(): void
    {
        [$organizationId] = $this->actorWith(['group.view', 'group.manage', 'schedule.manage']);

        $group = $this->group($organizationId, GroupStatus::Active);

        $this->get(GroupResource::getUrl('view', ['record' => $group], panel: 'admin'))
            ->assertOk();

        Livewire::test(GroupResource::getPages()['view']->getPage(), ['record' => $group->getKey()])
            ->assertActionVisible('schedule_sessions')
            ->assertActionHasUrl(
                'schedule_sessions',
                route('filament.admin.resources.schedules.create', ['group' => (string) $group->getKey()]),
            );
    }

    /** مجموعة في التخطيط لا تُجدوَل، فلا يُعرض طريق مآله رفض. */
    public function test_a_planning_group_does_not_offer_scheduling(): void
    {
        [$organizationId] = $this->actorWith(['group.view', 'group.manage', 'schedule.manage']);

        $group = $this->group($organizationId, GroupStatus::Planning);

        Livewire::test(GroupResource::getPages()['view']->getPage(), ['record' => $group->getKey()])
            ->assertActionHidden('schedule_sessions');
    }

    public function test_scheduling_link_is_hidden_without_the_scheduling_permission(): void
    {
        [$organizationId] = $this->actorWith(['group.view', 'group.manage']);

        $group = $this->group($organizationId, GroupStatus::Active);

        Livewire::test(GroupResource::getPages()['view']->getPage(), ['record' => $group->getKey()])
            ->assertActionHidden('schedule_sessions');
    }

    public function test_a_badge_is_awarded_to_a_user_from_the_badge_row(): void
    {
        [$organizationId, $actor] = $this->actorWith([
            'certificates.badge.view_any',
            'certificates.award.create',
        ]);

        $badge = $this->badge($organizationId);

        $this->get(BadgeFilamentResource::getUrl('index', panel: 'admin'));

        Livewire::test(BadgeFilamentResource::getPages()['index']->getPage())
            ->callTableAction('award', $badge, [
                'user_id' => (string) $actor->getKey(),
                'reason' => 'التزام متواصل بالحضور',
            ])
            ->assertHasNoTableActionErrors();

        $award = BadgeAward::query()->sole();

        self::assertSame((string) $badge->getKey(), (string) $award->badge_id);
        self::assertSame((string) $actor->getKey(), (string) $award->user_id);
    }

    public function test_awarding_is_hidden_without_the_award_permission(): void
    {
        [$organizationId] = $this->actorWith(['certificates.badge.view_any']);

        $badge = $this->badge($organizationId);

        $this->get(BadgeFilamentResource::getUrl('index', panel: 'admin'));

        Livewire::test(BadgeFilamentResource::getPages()['index']->getPage())
            ->assertTableActionHidden('award', $badge);

        self::assertSame(0, BadgeAward::query()->count());
    }

    private function group(string $organizationId, GroupStatus $status): Group
    {
        return Group::query()->create([
            'organization_id' => $organizationId,
            'code' => 'G-'.strtoupper(substr((string) Str::ulid(), -5)),
            'name' => ['ar' => 'مجموعة', 'en' => 'Group'],
            'capacity' => 10,
            'timezone' => 'UTC',
            'status' => $status,
            'starts_on' => now('UTC')->toDateString(),
        ]);
    }

    private function badge(string $organizationId): Badge
    {
        return Badge::query()->create([
            'organization_id' => $organizationId,
            'code' => 'B-'.strtoupper(substr((string) Str::ulid(), -5)),
            'name' => ['ar' => 'شارة المواظبة', 'en' => 'Attendance badge'],
            'description' => ['ar' => 'تُمنح للمواظبة', 'en' => 'For attendance'],
            'tier' => BadgeTier::Bronze,
            'is_active' => true,
        ]);
    }

    /**
     * @param list<string> $permissions
     * @return array{0: string, 1: User}
     */
    private function actorWith(array $permissions): array
    {
        $organization = Organization::factory()->create();
        $organizationId = (string) $organization->getKey();

        $actor = User::factory()->inOrganization($organizationId)->create();

        foreach ([...$permissions, 'admin.panel.access'] as $name) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $name],
                ['guard_name' => 'web'],
            );

            ModelHasPermission::query()->firstOrCreate([
                'permission_id' => (string) $permission->getKey(),
                'model_type' => $actor->getMorphClass(),
                'model_id' => (string) $actor->getAuthIdentifier(),
            ]);
        }

        app(PermissionGateRegistrar::class)->register();

        $this->actingAs($actor);
        session()->put('organization_id', $organizationId);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return [$organizationId, $actor];
    }
}
