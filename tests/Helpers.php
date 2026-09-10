<?php

declare(strict_types=1);
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Enums\GroupTeacherRole;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupMembership;
use Modules\Groups\Domain\Models\GroupProgram;
use Modules\Groups\Domain\Models\GroupTeacher;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Scheduling\Application\Actions\CreateScheduleAction;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Students\Domain\Models\StudentProfile;

/*
| دوال مساعدة عامة للاختبارات.
| تُحمَّل عبر composer autoload-dev files حتى تتوفر لكل الاختبارات،
| لأن Pest لا يحمّل Pest.php إلا من المجلدات المعرَّفة في phpunit.xml.
*/

/**
 * معرّف المؤسسة المستخدم في اختبارات هذا الموديول.
 */
function disciplineOrg(): string
{
    static $id = null;

    if ($id === null) {
        $id = (string) Str::ulid();
        DB::table('organizations')->insert([
            'id' => $id,
            'name' => json_encode(['ar' => 'مؤسسة الاختبار', 'en' => 'Test Org'], JSON_UNESCAPED_UNICODE),
            'slug' => 'test-'.strtolower(substr($id, -8)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return $id;
}

/** @return array<string, object> */
function schedulingFixture(): array
{
    $organization = Organization::factory()->create();
    $operator = User::factory()->inOrganization((string) $organization->id)->create(['name' => 'مدير الجدولة']);
    $teacherUser = User::factory()->inOrganization((string) $organization->id)->create(['name' => 'المعلم التشغيلي']);
    $studentUser = User::factory()->inOrganization((string) $organization->id)->create(['name' => 'الطالب التشغيلي']);
    $program = Program::factory()->create(['organization_id' => $organization->id]);
    $level = Level::factory()->create(['program_id' => $program->id]);
    $course = Course::factory()->create([
        'organization_id' => $organization->id,
        'level_id' => $level->id,
        'session_mode' => SessionMode::Group,
        'name' => ['ar' => 'كورس الجدولة الحقيقي', 'en' => 'Scheduling Course'],
    ]);
    $teacher = StaffProfile::query()->create([
        'organization_id' => $organization->id,
        'user_id' => $teacherUser->id,
        'staff_code' => 'T-SCHEDULE',
        'employment_type' => EmploymentType::Contractor,
        'gender' => StaffGender::Male,
        'hired_at' => '2026-01-01',
    ]);
    DB::table('teacher_courses')->insert([
        'id' => (string) Str::ulid(),
        'staff_profile_id' => $teacher->id,
        'course_id' => $course->id,
        'qualified_at' => now('UTC'),
        'qualified_by' => $operator->id,
        'created_at' => now('UTC'),
        'updated_at' => now('UTC'),
    ]);
    $student = StudentProfile::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $studentUser->id,
        'student_code' => 'ST-SCHEDULE',
    ]);
    Enrollment::query()->create([
        'organization_id' => $organization->id,
        'student_profile_id' => $student->id,
        'program_id' => $program->id,
        'current_level_id' => $level->id,
        'status' => EnrollmentStatus::Active,
        'applied_at' => now('UTC')->subMonth(),
        'activated_at' => now('UTC')->subWeeks(2),
    ]);
    $group = Group::query()->create([
        'organization_id' => $organization->id,
        'code' => 'GR-SCHEDULE',
        'name' => ['ar' => 'مجموعة الجدولة الحقيقية', 'en' => 'Scheduling Group'],
        'capacity' => 12,
        'timezone' => 'UTC',
        'status' => GroupStatus::Active,
        'starts_on' => '2026-10-01',
    ]);
    GroupProgram::query()->create(['group_id' => $group->id, 'program_id' => $program->id]);
    GroupTeacher::query()->create([
        'group_id' => $group->id,
        'staff_profile_id' => $teacher->id,
        'course_id' => $course->id,
        'role' => GroupTeacherRole::Lead,
        'assigned_from' => '2026-10-01',
    ]);
    GroupMembership::query()->create([
        'group_id' => $group->id,
        'student_profile_id' => $student->id,
        'joined_at' => now('UTC')->subDay(),
        'status' => MembershipStatus::Active,
    ]);

    return compact('organization', 'operator', 'teacher', 'student', 'program', 'level', 'course', 'group');
}

/**
 * @param array<string, object> $fixture
 * @param array<string, mixed> $overrides
 */
function createOperationalSchedule(array $fixture, array $overrides = []): Schedule
{
    return app(CreateScheduleAction::class)->execute(
        (string) $fixture['organization']->id,
        schedulePayload($fixture, $overrides),
        (string) $fixture['operator']->id,
        'إنشاء قالب جدول تشغيلي كامل للاختبار',
    );
}

/**
 * @param array<string, object> $fixture
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function schedulePayload(array $fixture, array $overrides = []): array
{
    return [
        'target_type' => 'group',
        'group_id' => (string) $fixture['group']->id,
        'course_id' => (string) $fixture['course']->id,
        'staff_profile_id' => (string) $fixture['teacher']->id,
        'weekdays' => [0],
        'interval_weeks' => 1,
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'timezone' => 'UTC',
        'starts_on' => '2026-10-11',
        'ends_on' => '2026-11-30',
        ...$overrides,
    ];
}
