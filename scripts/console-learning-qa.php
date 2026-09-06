<?php

declare(strict_types=1);

/** Explicit local-only fixture for the isolated console browser review. */
use App\Application\Actions\AssignStudentToGroupAction;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Modules\AccessControl\Domain\Contracts\RoleAssignmentGateway;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Assignments\Application\Actions\CreateAssignmentAction;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Identity\Application\Actions\ResetPassword;
use Modules\Identity\Domain\Models\User;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Students\Application\Actions\CreateStudentOnboardingAction;
use Modules\Students\Domain\Models\StudentProfile;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (!$app->environment('local') || config('database.connections.'.config('database.default').'.database') !== 'eschool_console_development'
    || DB::selectOne('select current_database() as name')->name !== 'eschool_console_development') {
    throw new RuntimeException('Fixture requires local environment and isolated console development database.');
}
app(PermissionGateRegistrar::class)->register();
$actors = User::query()->whereNotNull('organization_id')->get()->filter(static fn (User $user): bool => $user->can('admin.panel.access'));
if ($actors->pluck('organization_id')->unique()->count() !== 1) {
    throw new RuntimeException('Exactly one local QA organization required.');
}
$actor = $actors->firstOrFail();
$org = (string) $actor->organization_id;
$teacher = StaffProfile::query()->forOrganization($org)->where('staff_code', 'CONSOLE-DUES-QA')->firstOrFail();
$teacherUser = User::query()->findOrFail($teacher->user_id);
if ($teacherUser->email !== 'console-dues-qa@school.invalid') {
    throw new RuntimeException('Refusing to change a non-fixture account.');
}
$group = DB::table('groups')->where('organization_id', $org)->where('code', 'CONSOLE-DUES-QA')->sole();
$course = DB::table('courses')->where('organization_id', $org)->where('code', 'CONSOLE-DUES-QA')->sole();
$program = DB::table('levels')->where('id', $course->level_id)->value('program_id');
$region = DB::table('regions')->first();
if (!$region) {
    throw new RuntimeException('Seeded geography required.');
}
config(['mail.default' => 'log']);
Mail::fake();
Notification::fake();
Bus::fake();
Http::fake();
Event::fake();
$app->setLocale('ar');
auth()->setUser($actor);
$password = 'Console-QA!Sep2026-72'; // Synthetic local fixture password; never used outside the guarded database.
$result = DB::transaction(function () use ($org, $actor, $teacher, $teacherUser, $group, $course, $program, $region, $password): array {
    app(RoleAssignmentGateway::class)->assignIfMissing('teacher', User::class, $teacherUser->id, $org, $actor->id);
    app(ResetPassword::class)->execute($teacherUser->email, Password::broker()->createToken($teacherUser), $password);
    $studentUser = User::query()->where('email', 'console-learning-student@school.invalid')->first();
    if ($studentUser && $studentUser->organization_id !== $org) {
        throw new RuntimeException('Fixture identity belongs to another organization.');
    }
    if (!$studentUser) {
        $student = app(CreateStudentOnboardingAction::class)->execute([
            'account_mode' => 'new', 'full_name' => 'طالب تجربة بوابة التعلم QA', 'email' => 'console-learning-student@school.invalid',
            'username' => 'console.student.qa', 'password' => $password, 'password_confirmation' => $password,
            'locale' => 'ar', 'timezone' => 'Africa/Cairo', 'date_of_birth' => '2010-01-01', 'gender' => 'male',
            'country_id' => $region->country_id, 'region_id' => $region->id, 'preferred_program_id' => $program,
            'preferred_course_id' => $course->id, 'acceptance_reason' => 'بيانات QA محلية اصطناعية فقط', 'notes' => 'حساب تجربة محلي لا يمثل طالبًا حقيقيًا.',
        ], $org, $actor->id);
        $studentUser = User::query()->findOrFail($student->user_id);
        app(AssignStudentToGroupAction::class)->execute($org, $student->id, $program, $group->id, $course->id, $actor->id, reason: 'تسكين تجربة بوابة التعلم المحلية QA');
    } else {
        $student = StudentProfile::query()->forOrganization($org)->where('user_id', $studentUser->id)->firstOrFail();
        app(ResetPassword::class)->execute($studentUser->email, Password::broker()->createToken($studentUser), $password);
    }
    $enrollment = DB::table('enrollments')->where('organization_id', $org)->where('student_profile_id', $student->id)->where('program_id', $program)->where('status', 'active')->sole();
    $lessons = [];
    foreach ([1, 2] as $day) {
        $title = 'حصة تجربة بوابة التعلم QA '.$day;
        $lesson = Session::query()->forOrganization($org)->where('title->ar', $title)->first();
        if (!$lesson) {
            $start = CarbonImmutable::now('Africa/Cairo')->addDays($day)->setTime(18, 0)->utc();
            $lesson = Session::query()->create(['organization_id' => $org, 'course_id' => $course->id, 'group_id' => $group->id,
                'staff_profile_id' => $teacher->id, 'original_teacher_id' => $teacher->id, 'session_type' => 'regular', 'status' => SessionStatus::Scheduled,
                'scheduled_start' => $start, 'scheduled_end' => $start->addHour(), 'title' => ['ar' => $title]]);
            SessionParticipant::query()->create(['session_id' => $lesson->id, 'student_profile_id' => $student->id, 'enrollment_id' => $enrollment->id,
                'join_url_token' => Str::random(32), 'invited_at' => now(), 'attended_minutes' => 0]);
        }
        $lessons[] = $lesson->id;
    }
    $assignment = Assignment::query()->forOrganization($org)->where('title->ar', 'تدريب بوابة التعلم QA')->first();
    if (!$assignment) {
        $assignment = app(CreateAssignmentAction::class)->execute([
            'organization_id' => $org, 'course_id' => $course->id, 'group_id' => $group->id, 'staff_profile_id' => $teacher->id,
            'title' => ['ar' => 'تدريب بوابة التعلم QA'], 'instructions' => ['ar' => 'اقرأ الدرس ثم اكتب ملخصًا قصيرًا لما تعلمته. هذه مادة تجربة محلية.'],
            'assigned_at' => now()->toIso8601String(), 'due_at' => now()->addDays(3)->toIso8601String(), 'max_score' => 20, 'allows_late' => true, 'late_penalty_percent' => 0,
        ], $teacherUser->id, 'تكليف تجربة محلية QA');
    }

    return ['teacherLogin' => $teacherUser->email, 'studentLogin' => $studentUser->username, 'password' => $password,
        'teacherId' => $teacher->id, 'studentId' => $student->id, 'studentUserId' => $studentUser->id, 'organizationId' => $org, 'courseId' => $course->id, 'groupId' => $group->id, 'sessions' => $lessons, 'assignmentId' => $assignment->id];
});
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).PHP_EOL;
