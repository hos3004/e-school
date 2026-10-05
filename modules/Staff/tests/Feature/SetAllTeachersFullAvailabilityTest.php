<?php

declare(strict_types=1);

namespace Modules\Staff\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Staff\Application\Actions\SetAllTeachersFullAvailability;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;
use Shared\Support\BusinessRuleViolation;
use Tests\TestCase;

/**
 * إتاحة 24/7 الجماعية: تستبدل النوافذ القائمة، وتتجاوز المعلم المنتهية خدمته،
 * وتُسجَّل في التدقيق، وتقبل إعادة التشغيل دون تعارض.
 */
final class SetAllTeachersFullAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_replaces_existing_windows_with_a_full_week_for_active_teachers_only(): void
    {
        config(['scheduling.availability.teacher_requires_approval' => false]);
        [$organization, $actor] = $this->context();
        $withWindows = $this->teacher($organization);
        $withoutWindows = $this->teacher($organization);
        $terminated = $this->teacher($organization, terminated: true);
        $foreign = $this->teacher(Organization::factory()->create());
        $this->window($withWindows, weekday: 1, startTime: '09:00:00', endTime: '11:00:00');
        $this->window($terminated, weekday: 2, startTime: '09:00:00', endTime: '11:00:00');
        $reason = 'قرار المالك: إتاحة 24/7 لكل المعلمين الحاليين';

        $result = app(SetAllTeachersFullAvailability::class)->execute(
            (string) $organization->id, $reason, (string) $actor->id, 'UTC',
        );

        self::assertSame(['teachers' => 2, 'slots' => 14, 'removed' => 1, 'skipped' => []], $result);
        foreach ([$withWindows, $withoutWindows] as $teacher) {
            $slots = TeacherAvailability::query()->forProfile((string) $teacher->id)->get();
            self::assertCount(7, $slots);
            self::assertSame([0, 1, 2, 3, 4, 5, 6], $slots->pluck('weekday')->sort()->values()->all());
            foreach ($slots as $slot) {
                self::assertSame(TeacherAvailabilityApprovalStatus::Approved, $slot->approval_status);
                self::assertSame('00:00:00', $slot->start_time);
                self::assertSame('23:59:59', $slot->end_time);
                self::assertNull($slot->effective_to);
            }
        }
        self::assertSame(1, TeacherAvailability::query()->forProfile((string) $terminated->id)->count());
        self::assertSame('09:00:00', TeacherAvailability::query()->forProfile((string) $terminated->id)->value('start_time'));
        self::assertSame(0, TeacherAvailability::query()->forProfile((string) $foreign->id)->count());
        self::assertTrue(DB::table('audit_log')->where([
            'organization_id' => (string) $organization->id,
            'action' => 'staff.availability_bulk_set_all',
            'actor_id' => (string) $actor->id,
            'reason' => $reason,
        ])->exists());
    }

    public function test_running_twice_is_idempotent_and_never_leaves_overlaps(): void
    {
        config(['scheduling.availability.teacher_requires_approval' => false]);
        [$organization, $actor] = $this->context();
        $teacher = $this->teacher($organization);

        $first = app(SetAllTeachersFullAvailability::class)->execute((string) $organization->id, 'أول تشغيل', (string) $actor->id, 'UTC');
        $second = app(SetAllTeachersFullAvailability::class)->execute((string) $organization->id, 'إعادة تشغيل', (string) $actor->id, 'UTC');

        self::assertSame([], $first['skipped']);
        self::assertSame([], $second['skipped']);
        self::assertSame(7, $second['removed']);
        self::assertSame(7, TeacherAvailability::query()->forProfile((string) $teacher->id)->count());
    }

    public function test_a_written_reason_is_required(): void
    {
        [$organization] = $this->context();

        $this->expectException(BusinessRuleViolation::class);

        app(SetAllTeachersFullAvailability::class)->execute((string) $organization->id, '   ');
    }

    /** @return array{0: Organization, 1: User} */
    private function context(): array
    {
        $organization = Organization::factory()->create();

        return [$organization, User::factory()->inOrganization((string) $organization->id)->create()];
    }

    private function teacher(Organization $organization, bool $terminated = false): StaffProfile
    {
        $user = User::factory()->inOrganization((string) $organization->id)->create();

        return StaffProfile::query()->create([
            'organization_id' => (string) $organization->id,
            'user_id' => (string) $user->id,
            'staff_code' => 'TCH-FW-'.str()->random(8),
            'employment_type' => EmploymentType::Contractor,
            'hired_at' => CarbonImmutable::now('UTC')->subMonths(2)->toDateString(),
            'terminated_at' => $terminated ? CarbonImmutable::now('UTC') : null,
        ]);
    }

    private function window(StaffProfile $profile, int $weekday, string $startTime, string $endTime): TeacherAvailability
    {
        return TeacherAvailability::query()->create([
            'staff_profile_id' => (string) $profile->id,
            'weekday' => $weekday,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'timezone' => 'UTC',
            'effective_from' => CarbonImmutable::now('UTC')->subMonth()->startOfDay(),
            'approval_status' => TeacherAvailabilityApprovalStatus::Approved,
            'approved_at' => CarbonImmutable::now('UTC')->subMonth(),
        ]);
    }
}
