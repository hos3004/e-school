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
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Reporting\Domain\Models\StudentDashboard;
use Modules\Reporting\Presentation\Filament\Resources\StudentDashboardResource;
use Tests\TestCase;

/**
 * تصحيح عدّاد لوحة الطالب — إجراء كان مكتوبًا بلا زر.
 *
 * لوحة الطالب تُبنى بإسقاط الأحداث، وقد ينحرف عدّاد عن مصدره. بلا زر لم يكن
 * أمام المشرف إلا تعديل القاعدة يدويًا: يفسد الأثر ولا يترك سببًا.
 */
final class PanelLedgerActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_a_dashboard_counter_is_corrected_and_the_rate_recomputed(): void
    {
        [$organizationId] = $this->actorWith([
            'report.view', 'student.view.any', 'reporting.student.correct',
        ]);

        $dashboard = $this->dashboard($organizationId, total: 10, attended: 5);

        self::assertSame(5000, (int) $dashboard->attendance_rate_bp);

        Livewire::test(StudentDashboardResource::getPages()['index']->getPage())
            ->callTableAction('correct', $dashboard, [
                'column' => 'sessions_attended',
                'value' => 9,
                'reason' => 'حدث حضور ضاع أثناء انقطاع الطابور',
            ])
            ->assertHasNoTableActionErrors();

        $dashboard->refresh();

        self::assertSame(9, (int) $dashboard->sessions_attended);

        /*
         * الأثر الحقيقي: النسبة أُعيد حسابها لا مجرد تغيّر عدد.
         *
         * المقام `attended + missed` لا `sessions_total` — الحصص التي لم
         * تُحسم بعد لا تُحتسب على الطالب. فبعد تصحيح الحضور إلى 9 والغياب 5
         * تصير النسبة 9÷14 = 64.29٪ لا 90٪.
         */
        self::assertSame(6429, (int) $dashboard->attendance_rate_bp);
    }

    public function test_correction_refuses_a_column_outside_the_allowed_set(): void
    {
        [$organizationId] = $this->actorWith([
            'report.view', 'student.view.any', 'reporting.student.correct',
        ]);

        $dashboard = $this->dashboard($organizationId, total: 10, attended: 5);

        Livewire::test(StudentDashboardResource::getPages()['index']->getPage())
            ->callTableAction('correct', $dashboard, [
                'column' => 'attendance_rate_bp',
                'value' => 10000,
                'reason' => 'محاولة تغيير عمود محسوب',
            ])
            ->assertHasTableActionErrors(['column']);

        self::assertSame(5000, (int) $dashboard->refresh()->attendance_rate_bp);
    }

    public function test_correction_is_hidden_without_the_correction_permission(): void
    {
        [$organizationId] = $this->actorWith(['report.view', 'student.view.any']);

        $dashboard = $this->dashboard($organizationId, total: 10, attended: 5);

        Livewire::test(StudentDashboardResource::getPages()['index']->getPage())
            ->assertTableActionHidden('correct', $dashboard);
    }

    private function dashboard(string $organizationId, int $total, int $attended): StudentDashboard
    {
        return StudentDashboard::query()->create([
            'organization_id' => $organizationId,
            'enrollment_id' => (string) Str::ulid(),
            'student_profile_id' => (string) Str::ulid(),
            'sessions_total' => $total,
            'sessions_attended' => $attended,
            'sessions_missed' => $total - $attended,
            'attendance_rate_bp' => (int) round($attended / $total * 10000),
            'violations_count' => 0,
            'freezes_count' => 0,
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
        $this->get(StudentDashboardResource::getUrl('index', panel: 'admin'));

        return [$organizationId, $actor];
    }
}
