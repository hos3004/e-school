<?php

declare(strict_types=1);

namespace Modules\Payroll\Tests\Feature;

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Payroll\Domain\Models\PayrollAdjustment;
use Modules\Payroll\Presentation\Filament\Resources\PayrollAdjustmentResource;
use Tests\TestCase;

/**
 * دورة التسويات المالية — القناة الوحيدة لتصحيح دفتر append-only.
 *
 * الإجراءات الثلاثة كانت مكتوبة ولها سياسة كاملة **بلا مورد يعرضها**، فلم يكن
 * للتصحيح المالي مسار في اللوحة إطلاقًا — وهو ما يدفع إلى تعديل القاعدة يدويًا،
 * أي بالضبط ما يمنعه البند 4.
 *
 * الاختبار يثبّت القاعدة الحاكمة: **من يقترح لا يعتمد.**
 */
final class PayrollAdjustmentPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        config()->set('features.payroll', true);
    }

    public function test_an_approver_approves_an_adjustment_proposed_by_someone_else(): void
    {
        $organization = Organization::factory()->create();
        $organizationId = (string) $organization->getKey();

        $proposer = $this->user($organizationId);
        $approver = $this->actor($organizationId, [
            'payroll.view', 'payroll.adjustment.approve',
        ]);

        $adjustment = $this->adjustment($organizationId, $proposer);

        Livewire::test(PayrollAdjustmentResource::getPages()['index']->getPage())
            ->callTableAction('approve', $adjustment, ['reason' => 'مراجعة الحساب أثبتت استحقاق المكافأة'])
            ->assertHasNoTableActionErrors();

        $adjustment->refresh();

        self::assertNotNull($adjustment->approved_at);
        self::assertSame((string) $approver->getKey(), (string) $adjustment->approved_by);
        self::assertNull($adjustment->rejected_at);
    }

    /**
     * القاعدة التي تحمي المال: `requires_different_approver` في الإعداد،
     * وتفرضها السياسة. لو سقطت لأصبح بإمكان شخص واحد أن يمنح نفسه.
     */
    public function test_the_proposer_can_never_approve_their_own_adjustment(): void
    {
        $organization = Organization::factory()->create();
        $organizationId = (string) $organization->getKey();

        $actor = $this->actor($organizationId, [
            'payroll.view', 'payroll.adjustment.propose', 'payroll.adjustment.approve',
        ]);

        $adjustment = $this->adjustment($organizationId, $actor);

        Livewire::test(PayrollAdjustmentResource::getPages()['index']->getPage())
            ->assertTableActionHidden('approve', $adjustment);

        self::assertNull($adjustment->refresh()->approved_at);
    }

    public function test_rejection_records_the_reason_and_closes_the_adjustment(): void
    {
        $organization = Organization::factory()->create();
        $organizationId = (string) $organization->getKey();

        $proposer = $this->user($organizationId);
        $this->actor($organizationId, ['payroll.view', 'payroll.adjustment.approve']);

        $adjustment = $this->adjustment($organizationId, $proposer);

        Livewire::test(PayrollAdjustmentResource::getPages()['index']->getPage())
            ->callTableAction('reject', $adjustment, ['reason' => 'لا مستند يدعم المبلغ'])
            ->assertHasNoTableActionErrors();

        $adjustment->refresh();

        self::assertNotNull($adjustment->rejected_at);
        self::assertNull($adjustment->approved_at);
    }

    public function test_a_decision_reason_is_mandatory(): void
    {
        $organization = Organization::factory()->create();
        $organizationId = (string) $organization->getKey();

        $proposer = $this->user($organizationId);
        $this->actor($organizationId, ['payroll.view', 'payroll.adjustment.approve']);

        $adjustment = $this->adjustment($organizationId, $proposer);

        Livewire::test(PayrollAdjustmentResource::getPages()['index']->getPage())
            ->callTableAction('approve', $adjustment, ['reason' => ''])
            ->assertHasTableActionErrors(['reason']);

        self::assertNull($adjustment->refresh()->approved_at);
    }

    private function adjustment(string $organizationId, User $proposer): PayrollAdjustment
    {
        $periodId = (string) Str::ulid();
        $now = CarbonImmutable::now('UTC');

        DB::table('payroll_periods')->insert([
            'id' => $periodId,
            'organization_id' => $organizationId,
            'year' => $now->year,
            'month' => $now->month,
            'starts_on' => $now->startOfMonth()->toDateString(),
            'ends_on' => $now->endOfMonth()->toDateString(),
            'status' => 'open',
            'totals' => json_encode([], JSON_THROW_ON_ERROR),
        ]);

        return PayrollAdjustment::query()->create([
            'organization_id' => $organizationId,
            'payroll_period_id' => $periodId,
            'staff_profile_id' => (string) Str::ulid(),
            'type' => 'bonus',
            'amount' => 25000,
            'currency' => 'EGP',
            'reason' => 'مكافأة انضباط الحضور',
            'proposed_by' => (string) $proposer->getKey(),
            'proposed_at' => $now,
        ]);
    }

    private function user(string $organizationId): User
    {
        return User::factory()->inOrganization($organizationId)->create();
    }

    /**
     * @param list<string> $permissions
     */
    private function actor(string $organizationId, array $permissions): User
    {
        $actor = $this->user($organizationId);

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
        $this->get(PayrollAdjustmentResource::getUrl('index', panel: 'admin'));

        return $actor;
    }
}
