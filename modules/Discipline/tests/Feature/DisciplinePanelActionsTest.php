<?php

declare(strict_types=1);

namespace Modules\Discipline\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Discipline\Application\Actions\RecordViolationAction;
use Modules\Discipline\Domain\Enums\ReactivationStatus;
use Modules\Discipline\Domain\Models\ReactivationRequest;
use Modules\Discipline\Domain\Models\ViolationEvent;
use Modules\Discipline\Presentation\Filament\Resources\ReactivationRequestFilamentResource;
use Modules\Discipline\Presentation\Filament\Resources\ReactivationRequestFilamentResource\Pages\ListReactivationRequests;
use Modules\Discipline\Presentation\Filament\Resources\ViolationEventFilamentResource\Pages\ListViolationEvents;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Tests\TestCase;

/**
 * أزرار الانضباط في اللوحة — تُستدعى كما يستدعيها المستخدم، لا عبر الـAction.
 *
 * حتى 2026-09-02 كانت `DecideReactivationAction` و`WaiveViolationAction`
 * مكتوبتين ومختبَرتين ولهما سياستان (`decide` · `waive`) وصلاحيتان معلنتان —
 * ولا زرَّ في اللوحة يستدعيهما. الطالب المجمَّد يقدّم طلبًا، ولوحة المعلومات
 * تعرض «طلبات فك تجميد معلّقة»، ولا سبيل لإغلاق البند.
 *
 * لذلك يُقاس هنا الزرُّ لا الـAction: اختبارات الوحدة كانت خضراء طوال الوقت
 * والوظيفة معطّلة، فالخضرة وحدها لم تكن دليلًا.
 */
final class DisciplinePanelActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_an_approver_decides_a_reactivation_request_from_the_table(): void
    {
        [$organizationId, $actor] = $this->actorWith(['discipline.view_any', 'enrollment.reactivate']);
        $request = $this->pendingRequest($organizationId, $actor);
        $attemptId = (string) Str::ulid();

        Livewire::test(ListReactivationRequests::class)
            ->callTableAction('approve', $request, [
                'decision_note' => 'اجتاز اختبار الجدية وأوصى المرشد بإعادة التفعيل',
                'assessment_attempt_id' => $attemptId,
            ])
            ->assertHasNoTableActionErrors();

        $request->refresh();

        self::assertSame(ReactivationStatus::Approved, $request->status);
        self::assertSame($attemptId, (string) $request->assessment_attempt_id);
        self::assertSame((string) $actor->getKey(), (string) $request->reviewer_id);
        self::assertNotNull($request->reviewed_at);
        self::assertSame(
            'اجتاز اختبار الجدية وأوصى المرشد بإعادة التفعيل',
            $request->decision_note,
        );
    }

    /**
     * القبول بلا محاولة اختبار يجب أن يُردّ في النموذج، لا أن يصل إلى الـAction
     * فيرمي استثناءً يظهر للمستخدم كصفحة خطأ.
     */
    public function test_approving_without_an_assessment_attempt_is_rejected_by_the_form(): void
    {
        config()->set('discipline.reactivation.requires_assessment', true);

        [$organizationId, $actor] = $this->actorWith(['discipline.view_any', 'enrollment.reactivate']);
        $request = $this->pendingRequest($organizationId, $actor);

        Livewire::test(ListReactivationRequests::class)
            ->callTableAction('approve', $request, [
                'decision_note' => 'ملاحظة كافية الطول',
                'assessment_attempt_id' => '',
            ])
            ->assertHasTableActionErrors(['assessment_attempt_id']);

        self::assertSame(ReactivationStatus::Pending, $request->refresh()->status);
    }

    public function test_a_decision_note_is_mandatory(): void
    {
        [$organizationId, $actor] = $this->actorWith(['discipline.view_any', 'enrollment.reactivate']);
        $request = $this->pendingRequest($organizationId, $actor);

        Livewire::test(ListReactivationRequests::class)
            ->callTableAction('reject', $request, ['decision_note' => ''])
            ->assertHasTableActionErrors(['decision_note']);

        self::assertSame(ReactivationStatus::Pending, $request->refresh()->status);
    }

    public function test_a_viewer_without_the_approver_permission_never_sees_the_decision_buttons(): void
    {
        [$organizationId, $actor] = $this->actorWith(['discipline.view_any']);
        $request = $this->pendingRequest($organizationId, $actor);

        Livewire::test(ListReactivationRequests::class)
            ->assertTableActionHidden('approve', $request)
            ->assertTableActionHidden('reject', $request);
    }

    public function test_waiving_a_violation_stops_it_counting_toward_escalation(): void
    {
        [$organizationId, $actor] = $this->actorWith(['discipline.view_any', 'discipline.waive_violations']);

        $violation = app(RecordViolationAction::class)->execute([
            'organization_id' => $organizationId,
            'enrollment_id' => (string) Str::ulid(),
            'student_profile_id' => (string) Str::ulid(),
            'type' => 'unexcused_absence',
        ]);

        $countable = fn (): int => ViolationEvent::query()
            ->where('enrollment_id', $violation->enrollment_id)
            ->countable()
            ->count();

        self::assertSame(1, $countable());

        Livewire::test(ListViolationEvents::class)
            ->callTableAction('waive', $violation, ['reason' => 'عذر طبي موثّق من المستشفى'])
            ->assertHasNoTableActionErrors();

        $violation->refresh();

        self::assertTrue($violation->isWaived());
        self::assertSame('عذر طبي موثّق من المستشفى', $violation->waiver_reason);
        self::assertSame((string) $actor->getKey(), (string) $violation->waived_by);
        // الأثر الحقيقي: خرجت من نافذة الاحتساب فلا تُصعِّد.
        self::assertSame(0, $countable());
    }

    public function test_waiving_requires_its_own_permission(): void
    {
        [$organizationId] = $this->actorWith(['discipline.view_any']);

        $violation = app(RecordViolationAction::class)->execute([
            'organization_id' => $organizationId,
            'enrollment_id' => (string) Str::ulid(),
            'student_profile_id' => (string) Str::ulid(),
            'type' => 'unexcused_absence',
        ]);

        Livewire::test(ListViolationEvents::class)
            ->assertTableActionHidden('waive', $violation);

        self::assertFalse($violation->refresh()->isWaived());
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

        /*
         * `admin.panel.access` ليست تفصيلًا: بدونها ترفض اللوحة الوصول فلا
         * يُقلع مكوّن Livewire أصلًا، ويظهر الفشل كـ«mountedActions on null»
         * وكأنه عطب في الاختبار لا في الصلاحيات.
         *
         * و`discipline.view_any` و`discipline.waive_violations` غير مزروعتين في
         * `AccessControlSeeder` رغم أن السياسات تعتمد عليهما، فتُنشأ هنا.
         */
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

        // بلا إعادة التسجيل تبقى القدرات غير معرَّفة في الـGate.
        app(PermissionGateRegistrar::class)->register();

        $this->actingAs($actor);
        session()->put('organization_id', $organizationId);

        // بعد actingAs لا قبله: مكوّن Livewire يُقلع في سياق اللوحة الحالية،
        // وضبطها قبل حسم المستخدم يترك instance() فارغًا فتسقط كل الاستدعاءات.
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // طلب حقيقي أولًا: هو ما يُطلق `ServingFilament` فتُسجَّل مكوّنات اللوحة
        // ويُضبط التنقّل. بدونه يعود `Livewire::test()->instance()` فارغًا.
        $this->get(ReactivationRequestFilamentResource::getUrl('index', panel: 'admin'));

        return [$organizationId, $actor];
    }

    private function pendingRequest(string $organizationId, User $actor): ReactivationRequest
    {
        return ReactivationRequest::query()->create([
            'organization_id' => $organizationId,
            'enrollment_id' => (string) Str::ulid(),
            'requested_by' => (string) $actor->getKey(),
            'status' => ReactivationStatus::Pending,
            'attempt_number' => 1,
            'student_statement' => 'أتعهد بالالتزام بالحضور والانضباط.',
        ]);
    }
}
