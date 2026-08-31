<?php

declare(strict_types=1);

namespace Modules\Staff\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Staff\Application\Actions\AssignTeacherQualificationsAction;
use Modules\Staff\Application\Actions\RevokeTeacherQualificationAction;
use Modules\Staff\Domain\Contracts\TeacherQualificationQueries;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Models\StaffProfile;
use Shared\Support\BusinessRuleViolation;
use Tests\TestCase;

final class TeacherQualificationRevocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_revokes_qualification_with_reason_and_audit_without_hard_delete(): void
    {
        [$organization, $admin, $profile, $courseId] = $this->context();

        app(RevokeTeacherQualificationAction::class)->execute(
            profile: $profile,
            courseId: $courseId,
            actorId: (string) $admin->id,
            reason: 'انتهت الحاجة لاعتماد هذا الكورس بعد مراجعة الخطة',
        );

        // السجل باقٍ معلَّقًا لا محذوفًا.
        $qualificationId = (string) DB::table('teacher_courses')->value('id');
        self::assertSame(1, DB::table('teacher_courses')->count());

        /** @var TeacherQualificationQueries $queries */
        $queries = app(TeacherQualificationQueries::class);

        self::assertFalse($queries->isQualified((string) $profile->id, $courseId));
        self::assertNotContains($courseId, $queries->courseIdsForTeacher((string) $profile->id));
        self::assertNotContains((string) $profile->id, $queries->qualifiedTeacherIdsForCourse($courseId));

        self::assertTrue(DB::table('audit_log')->where([
            'action' => 'staff.qualification_revoked',
            'auditable_id' => $qualificationId,
            'reason' => 'انتهت الحاجة لاعتماد هذا الكورس بعد مراجعة الخطة',
        ])->exists());
    }

    public function test_reassignment_reactivates_the_same_record(): void
    {
        [, $admin, $profile, $courseId] = $this->context();

        app(RevokeTeacherQualificationAction::class)->execute(
            profile: $profile,
            courseId: $courseId,
            actorId: (string) $admin->id,
            reason: 'إيقاف مؤقت للاعتماد',
        );

        $assigned = app(AssignTeacherQualificationsAction::class)->execute(
            profile: $profile,
            courseIds: [$courseId],
            actorId: (string) $admin->id,
            reason: 'إعادة اعتماد الكورس بعد استكمال التأهيل',
        );

        self::assertCount(1, $assigned);
        self::assertSame(1, DB::table('teacher_courses')->count());
        self::assertNull(DB::table('teacher_courses')->value('revoked_at'));

        self::assertTrue(app(TeacherQualificationQueries::class)
            ->isQualified((string) $profile->id, $courseId));
    }

    public function test_cannot_revoke_twice(): void
    {
        [, $admin, $profile, $courseId] = $this->context();

        $action = app(RevokeTeacherQualificationAction::class);
        $action->execute($profile, $courseId, (string) $admin->id, 'إلغاء أول');

        $this->expectException(BusinessRuleViolation::class);
        $action->execute($profile, $courseId, (string) $admin->id, 'إلغاء مكرر');
    }

    public function test_requires_written_reason(): void
    {
        [, $admin, $profile, $courseId] = $this->context();

        $this->expectException(BusinessRuleViolation::class);
        app(RevokeTeacherQualificationAction::class)->execute($profile, $courseId, (string) $admin->id, '');
    }

    /** @return array{0: Organization, 1: User, 2: StaffProfile, 3: string} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->inOrganization((string) $organization->id)->create();
        $teacher = User::factory()->inOrganization((string) $organization->id)->create();
        $profile = StaffProfile::query()->create([
            'organization_id' => (string) $organization->id,
            'user_id' => (string) $teacher->id,
            'staff_code' => 'TCH-RV-'.str()->random(4),
            'employment_type' => EmploymentType::Contractor,
            'gender' => StaffGender::Female,
            'hired_at' => now()->subYear()->toDateString(),
        ]);
        $program = Program::factory()->create([
            'organization_id' => (string) $organization->id,
            'is_active' => true,
        ]);
        $level = Level::factory()->create(['program_id' => (string) $program->id]);
        $course = Course::factory()->create([
            'organization_id' => (string) $organization->id,
            'level_id' => (string) $level->id,
            'is_active' => true,
            'session_mode' => SessionMode::Group,
        ]);
        $courseId = (string) $course->id;

        app(AssignTeacherQualificationsAction::class)->execute(
            profile: $profile,
            courseIds: [$courseId],
            actorId: (string) $admin->id,
            reason: 'اعتماد أولي للكورس بعد اجتياز التأهيل',
        );

        return [$organization, $admin, $profile, $courseId];
    }
}
