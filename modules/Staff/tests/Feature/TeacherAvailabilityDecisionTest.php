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

    /** @return array{Organization, User, TeacherAvailability} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->inOrganization((string) $organization->id)->create();
        $teacher = User::factory()->inOrganization((string) $organization->id)->create();
        $profile = StaffProfile::query()->create([
            'organization_id' => (string) $organization->id,
            'user_id' => (string) $teacher->id,
            'staff_code' => 'TCH-AVAILABILITY-001',
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
