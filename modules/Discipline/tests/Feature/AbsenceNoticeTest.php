<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Discipline\Application\Actions\RecordViolationAction;
use Modules\Discipline\Domain\Enums\ViolationType;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupProgram;
use Modules\Identity\Domain\Models\User;
use Modules\Notifications\Database\Seeders\NotificationTemplateSeeder;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Organization\Domain\Models\Organization;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Students\Domain\Models\StudentProfile;

/*
 * إخطار الغياب.
 *
 * قرار المدرسة: رسالة عند كل غياب لا عند العتبة وحدها، وفيها تنبيه لبق بأن
 * بلوغ العتبة يعني تجميد القيد آليًا. والعتبة تُقرأ من config لا من نص القالب،
 * فلو غيّرت المدرسة سياستها تغيّر نص الرسالة معها.
 */
uses(RefreshDatabase::class);

function absenceNoticeFixture(): array
{
    $organization = Organization::factory()->create();
    $teacherUser = User::factory()->inOrganization((string) $organization->id)->create(['name' => 'معلم الغياب']);
    $studentUser = User::factory()->inOrganization((string) $organization->id)->create(['name' => 'طالب الغياب']);
    $program = Program::factory()->create([
        'organization_id' => $organization->id,
        'name' => ['ar' => 'برنامج الغياب', 'en' => 'Absence Program'],
    ]);
    $level = Level::factory()->create(['program_id' => $program->id]);
    $course = Course::factory()->create([
        'organization_id' => $organization->id,
        'level_id' => $level->id,
        'name' => ['ar' => 'مقرر الغياب', 'en' => 'Absence Course'],
        'session_mode' => SessionMode::Group,
    ]);
    $teacher = StaffProfile::query()->create([
        'organization_id' => $organization->id,
        'user_id' => $teacherUser->id,
        'staff_code' => 'T-ABS',
        'employment_type' => EmploymentType::Contractor,
        'gender' => StaffGender::Male,
        'hired_at' => '2026-01-01',
    ]);
    $student = StudentProfile::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $studentUser->id,
        'student_code' => 'ST-ABS',
    ]);
    $enrollment = Enrollment::query()->create([
        'organization_id' => $organization->id,
        'student_profile_id' => $student->id,
        'program_id' => $program->id,
        'current_level_id' => $level->id,
        'status' => EnrollmentStatus::Active,
        'applied_at' => now('UTC')->subMonth(),
        'activated_at' => now('UTC')->subWeek(),
    ]);
    $group = Group::query()->create([
        'organization_id' => $organization->id,
        'code' => 'GR-ABS',
        'name' => ['ar' => 'مجموعة الغياب', 'en' => 'Absence Group'],
        'capacity' => 10,
        'timezone' => 'Europe/Istanbul',
        'status' => GroupStatus::Active,
        'starts_on' => '2026-01-01',
    ]);
    GroupProgram::query()->create(['group_id' => $group->id, 'program_id' => $program->id]);
    $session = Session::query()->create([
        'organization_id' => $organization->id,
        'group_id' => $group->id,
        'course_id' => $course->id,
        'staff_profile_id' => $teacher->id,
        'original_teacher_id' => $teacher->id,
        'session_type' => 'group',
        'status' => SessionStatus::Scheduled,
        'scheduled_start' => CarbonImmutable::now('UTC')->subHours(2),
        'scheduled_end' => CarbonImmutable::now('UTC')->subHour(),
        'title' => ['ar' => 'حصة الغياب', 'en' => 'Absence Session'],
    ]);
    SessionParticipant::query()->create([
        'session_id' => $session->id,
        'student_profile_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'join_url_token' => Str::random(64),
        'invited_at' => now('UTC')->subDay(),
        'attended_minutes' => 0,
    ]);

    config([
        'notifications.channels' => [
            'in_app' => ['enabled' => true, 'gateway' => \Modules\Notifications\Infrastructure\Gateways\InAppChannelGateway::class],
        ],
        'notifications.quiet_hours.enabled' => false,
    ]);

    return compact('organization', 'studentUser', 'student', 'enrollment', 'session');
}

function recordAbsence(array $fixture): void
{
    app(RecordViolationAction::class)->execute([
        'organization_id' => (string) $fixture['organization']->id,
        'enrollment_id' => (string) $fixture['enrollment']->id,
        'student_profile_id' => (string) $fixture['student']->id,
        'session_id' => (string) $fixture['session']->id,
        'source_event_id' => (string) Str::ulid(),
        'type' => ViolationType::UnexcusedAbsence,
    ]);
}

it('notifies the student on every absence with the configured freeze threshold', function (): void {
    $fixture = absenceNoticeFixture();
    $this->seed(NotificationTemplateSeeder::class);

    recordAbsence($fixture);

    $row = NotificationOutbox::query()
        ->where('event_name', 'discipline.absence_recorded')
        ->where('user_id', $fixture['studentUser']->id)
        ->sole();

    expect($row->category)->toBe('absence_notice')
        ->and($row->body['ar'])->toContain('طالب الغياب')
        ->and($row->body['ar'])->toContain('حصة الغياب')
        // العتبة من config/discipline.php، لا رقم مكتوب في القالب.
        ->and($row->body['ar'])->toContain('تجميد القيد تلقائيًا')
        ->and($row->body['ar'])->toContain('3 مرات')
        ->and($row->body['ar'])->not->toContain('{{');
});

it('sends a fresh notice for each absence, not only at the escalation thresholds', function (): void {
    $fixture = absenceNoticeFixture();
    $this->seed(NotificationTemplateSeeder::class);

    recordAbsence($fixture);
    recordAbsence($fixture);
    recordAbsence($fixture);

    $rows = NotificationOutbox::query()
        ->where('event_name', 'discipline.absence_recorded')
        ->where('user_id', $fixture['studentUser']->id)
        ->get();

    // العدّاد يتقدم مع كل غياب فيظهر الرقم الصحيح في كل رسالة، بينما تبقى
    // جملة العتبة نفسها في الثلاث — فالتمييز بالعدّاد لا بالعتبة.
    expect($rows)->toHaveCount(3)
        ->and($rows->filter(fn ($row): bool => str_contains($row->body['ar'], 'الغياب 1 خلال')))->toHaveCount(1)
        ->and($rows->filter(fn ($row): bool => str_contains($row->body['ar'], 'الغياب 2 خلال')))->toHaveCount(1)
        ->and($rows->filter(fn ($row): bool => str_contains($row->body['ar'], 'الغياب 3 خلال')))->toHaveCount(1)
        ->and($rows->filter(fn ($row): bool => str_contains($row->body['ar'], 'بلوغ الغياب 3 مرات')))->toHaveCount(3);
});

it('stays silent for absences the discipline policy does not count', function (): void {
    $fixture = absenceNoticeFixture();
    $this->seed(NotificationTemplateSeeder::class);

    app(RecordViolationAction::class)->execute([
        'organization_id' => (string) $fixture['organization']->id,
        'enrollment_id' => (string) $fixture['enrollment']->id,
        'student_profile_id' => (string) $fixture['student']->id,
        'session_id' => (string) $fixture['session']->id,
        'source_event_id' => (string) Str::ulid(),
        'type' => ViolationType::ExcusedAbsence,
    ]);

    expect(NotificationOutbox::query()->where('event_name', 'discipline.absence_recorded')->count())->toBe(0);
});
