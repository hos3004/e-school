<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupProgram;
use Modules\Identity\Domain\Models\User;
use Modules\Notifications\Database\Seeders\NotificationTemplateSeeder;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Infrastructure\Gateways\InAppChannelGateway;
use Modules\Organization\Domain\Models\Organization;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Modules\Sessions\Domain\Models\SessionReminderDispatch;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Students\Domain\Models\StudentProfile;

/*
 * مراحل التذكير وروابط الدخول.
 *
 * ما يجب أن يثبت هنا:
 *  · التنبيه المبكر ورابط الدخول مرحلتان مستقلتان لا تُلغي إحداهما الأخرى.
 *  · رابط المعلم داخل النظام (تسجيل الدخول شرط احتساب الحضور والمستحقات)
 *    ورابط الطالب موقّع ومربوط بمشاركته وحده — واختلافهما هو جوهر الطلب.
 *  · تكرار تشغيل المجدول لا يكرر الرسائل.
 */
uses(RefreshDatabase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/**
 * @param array<string, mixed> $sessionOverrides
 * @return array<string, mixed>
 */
function joinLinkFixture(array $sessionOverrides = []): array
{
    $organization = Organization::factory()->create();
    $teacherUser = User::factory()->inOrganization((string) $organization->id)->create(['name' => 'معلم الروابط']);
    $studentUser = User::factory()->inOrganization((string) $organization->id)->create(['name' => 'طالب الروابط']);
    $program = Program::factory()->create([
        'organization_id' => $organization->id,
        'name' => ['ar' => 'برنامج الروابط', 'en' => 'Links Program'],
    ]);
    $level = Level::factory()->create(['program_id' => $program->id]);
    $course = Course::factory()->create([
        'organization_id' => $organization->id,
        'level_id' => $level->id,
        'name' => ['ar' => 'مقرر الروابط', 'en' => 'Links Course'],
        'session_mode' => SessionMode::Group,
    ]);
    $teacher = StaffProfile::query()->create([
        'organization_id' => $organization->id,
        'user_id' => $teacherUser->id,
        'staff_code' => 'T-LINK',
        'employment_type' => EmploymentType::Contractor,
        'gender' => StaffGender::Male,
        'hired_at' => '2026-01-01',
    ]);
    $student = StudentProfile::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $studentUser->id,
        'student_code' => 'ST-LINK',
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
        'code' => 'GR-LINK',
        'name' => ['ar' => 'مجموعة الروابط', 'en' => 'Links Group'],
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
        'scheduled_start' => CarbonImmutable::now('UTC')->addMinutes(10),
        'scheduled_end' => CarbonImmutable::now('UTC')->addMinutes(70),
        'title' => ['ar' => 'حصة الروابط', 'en' => 'Links Session'],
        ...$sessionOverrides,
    ]);
    $participant = SessionParticipant::query()->create([
        'session_id' => $session->id,
        'student_profile_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'join_url_token' => Str::random(64),
        'invited_at' => now('UTC'),
        'attended_minutes' => 0,
    ]);

    return compact('organization', 'teacherUser', 'studentUser', 'session', 'participant');
}

function joinLinkOnlyInApp(): void
{
    config([
        'notifications.channels' => [
            'in_app' => ['enabled' => true, 'gateway' => InAppChannelGateway::class],
        ],
        'notifications.quiet_hours.enabled' => false,
    ]);
}

it('sends each side the link that belongs to it once the join window opens', function (): void {
    joinLinkOnlyInApp();
    $fixture = joinLinkFixture([
        'scheduled_start' => CarbonImmutable::now('UTC')->addMinutes(10),
        'scheduled_end' => CarbonImmutable::now('UTC')->addMinutes(70),
    ]);
    $this->seed(NotificationTemplateSeeder::class);

    $this->artisan('sessions:dispatch-reminders')->assertSuccessful();

    $teacherRow = NotificationOutbox::query()
        ->where('event_name', 'session.join_link.teacher')
        ->where('user_id', $fixture['teacherUser']->id)
        ->sole();
    $studentRow = NotificationOutbox::query()
        ->where('event_name', 'session.join_link.student')
        ->where('user_id', $fixture['studentUser']->id)
        ->sole();

    $teacherBody = $teacherRow->body['ar'];
    $studentBody = $studentRow->body['ar'];

    expect($teacherBody)->toContain(route('learning.teacher.sessions.show', [
        'session' => (string) $fixture['session']->id,
    ]))
        // رابط المعلم داخل النظام عمدًا: الدخول بالحساب هو ما يحتسب الحضور.
        ->and($teacherBody)->not->toContain('/classroom/student-link/')
        // رابط الطالب موقّع ومربوط بمشاركته هو، فلا يصلح لطالب آخر.
        ->and($studentBody)->toContain('/classroom/student-link/'.$fixture['session']->id.'/'.$fixture['participant']->id)
        ->and($studentBody)->toContain('signature=')
        ->and($studentBody)->toContain('expires=');
});

it('keeps the early reminder and the join link as two independent stages', function (): void {
    joinLinkOnlyInApp();
    $fixture = joinLinkFixture([
        'scheduled_start' => CarbonImmutable::now('UTC')->addMinutes(90),
        'scheduled_end' => CarbonImmutable::now('UTC')->addMinutes(150),
    ]);
    $this->seed(NotificationTemplateSeeder::class);

    // على بُعد 90 دقيقة: التنبيه المبكر فقط، ولم تُفتح نافذة الروابط بعد.
    $this->artisan('sessions:dispatch-reminders')->assertSuccessful();

    expect(NotificationOutbox::query()->where('event_name', 'session.approaching')->count())->toBe(2)
        ->and(NotificationOutbox::query()->where('event_name', 'like', 'session.join_link%')->count())->toBe(0);

    // بعد أن يقترب الموعد تُرسل الروابط، والتنبيه المبكر لا يُعاد.
    CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->addMinutes(80));
    $this->artisan('sessions:dispatch-reminders')->assertSuccessful();

    expect(NotificationOutbox::query()->where('event_name', 'session.approaching')->count())->toBe(2)
        ->and(NotificationOutbox::query()->where('event_name', 'session.join_link.teacher')->count())->toBe(1)
        ->and(NotificationOutbox::query()->where('event_name', 'session.join_link.student')->count())->toBe(1)
        ->and(SessionReminderDispatch::query()->where('session_id', $fixture['session']->id)->count())->toBe(3);
});

it('still sends join links for a session that already started', function (): void {
    joinLinkOnlyInApp();
    $fixture = joinLinkFixture([
        'scheduled_start' => CarbonImmutable::now('UTC')->addMinutes(10),
        'scheduled_end' => CarbonImmutable::now('UTC')->addMinutes(70),
        // المعلم يستطيع فتح الفصل قبل الموعد، فتصير الحصة جارية قبل أن يحين
        // وقت رابط الطالب. رُصدت هذه الحالة على الإنتاج لا في التخطيط.
        'status' => SessionStatus::InProgress,
    ]);
    $this->seed(NotificationTemplateSeeder::class);

    $this->artisan('sessions:dispatch-reminders')->assertSuccessful();

    expect(NotificationOutbox::query()->where('event_name', 'session.join_link.teacher')->count())->toBe(1)
        ->and(NotificationOutbox::query()->where('event_name', 'session.join_link.student')->count())->toBe(1)
        // التنبيه المبكر لا يخص حصة بدأت، فلا يُرسل لها.
        ->and(NotificationOutbox::query()->where('event_name', 'session.approaching')->count())->toBe(0);
});

it('never sends join links for a cancelled session', function (): void {
    joinLinkOnlyInApp();
    joinLinkFixture([
        'scheduled_start' => CarbonImmutable::now('UTC')->addMinutes(10),
        'scheduled_end' => CarbonImmutable::now('UTC')->addMinutes(70),
        'status' => SessionStatus::CancelledBySchool,
    ]);
    $this->seed(NotificationTemplateSeeder::class);

    $this->artisan('sessions:dispatch-reminders')->assertSuccessful();

    expect(NotificationOutbox::query()->where('event_name', 'like', 'session.join_link%')->count())->toBe(0);
});

it('does not repeat a stage when the scheduler runs again', function (): void {
    joinLinkOnlyInApp();
    joinLinkFixture();
    $this->seed(NotificationTemplateSeeder::class);

    $this->artisan('sessions:dispatch-reminders')->assertSuccessful();
    $this->artisan('sessions:dispatch-reminders')->assertSuccessful();
    $this->artisan('sessions:dispatch-reminders')->assertSuccessful();

    expect(NotificationOutbox::query()->where('event_name', 'session.join_link.teacher')->count())->toBe(1)
        ->and(NotificationOutbox::query()->where('event_name', 'session.join_link.student')->count())->toBe(1)
        ->and(NotificationOutbox::query()->where('event_name', 'session.approaching')->count())->toBe(2);
});

it('stops issuing student links when the signed-link feature is switched off', function (): void {
    joinLinkOnlyInApp();
    config(['virtual-classroom.student_link.enabled' => false]);
    joinLinkFixture();
    $this->seed(NotificationTemplateSeeder::class);

    $this->artisan('sessions:dispatch-reminders')->assertSuccessful();

    expect(NotificationOutbox::query()->where('event_name', 'session.join_link.student')->count())->toBe(0)
        ->and(NotificationOutbox::query()->where('event_name', 'session.join_link.teacher')->count())->toBe(1);
});
