<?php

declare(strict_types=1);

namespace Modules\Staff\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Staff\Application\Actions\DecideTeacherAvailabilityAction;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Events\TeacherAvailabilityApproved;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;
use Shared\Support\BusinessRuleViolation;
use Tests\TestCase;

final class TeacherAvailabilityDecisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([TeacherAvailabilityApproved::class]);
    }

    public function test_pending_availability_can_be_approved_with_reason_and_audit(): void
    {
        [$organization, $actor, $availability] = $this->context();
        $reason = 'الفترة مناسبة للجدول المعتمد ولا تتعارض مع التكليف الحالي';

        $decided = app(DecideTeacherAvailabilityAction::class)->execute(
            $availability,
            TeacherAvailabilityApprovalStatus::Approved,
            (string) $actor->id,
            $reason,
        );

        self::assertSame(TeacherAvailabilityApprovalStatus::Approved, $decided->approval_status);
        self::assertSame((string) $actor->id, $decided->decided_by);
        self::assertSame($reason, $decided->decision_reason);
        self::assertNotNull($decided->approved_at);
        self::assertTrue(DB::table('audit_log')->where([
            'organization_id' => (string) $organization->id,
            'action' => 'staff.availability_decided',
            'auditable_id' => (string) $availability->id,
            'reason' => $reason,
        ])->exists());
        Event::assertDispatched(TeacherAvailabilityApproved::class);
    }

    public function test_pending_availability_can_be_rejected_and_the_terminal_decision_cannot_be_rewritten(): void
    {
        [, $actor, $availability] = $this->context();

        $decided = app(DecideTeacherAvailabilityAction::class)->execute(
            $availability,
            TeacherAvailabilityApprovalStatus::Rejected,
            (string) $actor->id,
            'تتعارض الفترة مع جدول قائم للمعلم',
        );

        self::assertSame(TeacherAvailabilityApprovalStatus::Rejected, $decided->approval_status);
        self::assertNull($decided->approved_at);
        Event::assertNotDispatched(TeacherAvailabilityApproved::class);

        try {
            app(DecideTeacherAvailabilityAction::class)->execute(
                $decided,
                TeacherAvailabilityApprovalStatus::Approved,
                (string) $actor->id,
                'محاولة تغيير قرار نهائي',
            );

            self::fail('A terminal availability decision must not be rewritten.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('staff.availability_invalid_approval_transition', $violation->rule);
        }
    }

    public function test_policy_activation_is_scoped_dry_by_default_idempotent_and_audited_without_notifications(): void
    {
        config()->set('scheduling.availability.teacher_requires_approval', false);
        [$organization, , $pending] = $this->context();
        [, , $foreign] = $this->context();
        $rejected = $pending->replicate();
        $rejected->id = (string) str()->ulid();
        $rejected->approval_status = TeacherAvailabilityApprovalStatus::Rejected;
        $rejected->weekday = 2;
        $rejected->save();

        $this->artisan('staff:activate-pending-availability', ['--organization' => $organization->id])->assertSuccessful();
        self::assertSame(TeacherAvailabilityApprovalStatus::Pending, $pending->fresh()->approval_status);
        $this->artisan('staff:activate-pending-availability', ['--organization' => $organization->id, '--apply' => true])->assertSuccessful();

        self::assertSame(TeacherAvailabilityApprovalStatus::Approved, $pending->fresh()->approval_status);
        self::assertNull($pending->fresh()->approved_by);
        self::assertSame(TeacherAvailabilityApprovalStatus::Rejected, $rejected->fresh()->approval_status);
        self::assertSame(TeacherAvailabilityApprovalStatus::Pending, $foreign->fresh()->approval_status);
        $this->assertDatabaseHas('audit_log', [
            'organization_id' => $organization->id,
            'auditable_id' => $pending->id,
            'action' => 'staff.availability_activated',
            'actor_type' => 'system',
            'actor_id' => null,
        ]);
        $this->artisan('staff:activate-pending-availability', ['--organization' => $organization->id, '--apply' => true])->assertSuccessful();
        self::assertSame(1, DB::table('audit_log')->where('action', 'staff.availability_activated')->count());
        Event::assertNotDispatched(TeacherAvailabilityApproved::class);
    }

    public function test_policy_activation_requires_explicit_scope_and_disabled_review_policy(): void
    {
        [$organization, , $pending] = $this->context();
        $this->artisan('staff:activate-pending-availability', ['--apply' => true])->assertFailed();
        config()->set('scheduling.availability.teacher_requires_approval', true);
        $this->artisan('staff:activate-pending-availability', ['--organization' => $organization->id, '--apply' => true])->assertFailed();
        self::assertSame(TeacherAvailabilityApprovalStatus::Pending, $pending->fresh()->approval_status);
    }

    /** @return array{Organization, User, TeacherAvailability} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->inOrganization((string) $organization->id)->create();
        $teacher = User::factory()->inOrganization((string) $organization->id)->create();
        $profile = StaffProfile::query()->create([
            'organization_id' => (string) $organization->id,
            'user_id' => (string) $teacher->id,
            'staff_code' => 'TCH-AV-'.str()->random(8),
            'employment_type' => EmploymentType::Contractor,
            'gender' => StaffGender::Female,
            'hired_at' => now()->toDateString(),
        ]);
        $availability = TeacherAvailability::query()->create([
            'staff_profile_id' => (string) $profile->id,
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'timezone' => 'UTC',
            'effective_from' => now()->toDateString(),
            'approval_status' => TeacherAvailabilityApprovalStatus::Pending,
        ]);

        return [$organization, $actor, $availability];
    }
}
