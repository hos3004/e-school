<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Academics\Domain\Models\Course;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Identity\Domain\Models\User;
use Modules\Notifications\Database\Seeders\NotificationTemplateSeeder;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Infrastructure\Gateways\InAppChannelGateway;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Testing\Fixtures;
use Tests\TestCase;

/**
 * المراسلة اليدوية من الكونسول.
 *
 * ما يجب أن يثبت هنا: الجمهور يقيّد المستلمين فعلًا لا في الواجهة وحدها،
 * وإرسال كلمة المرور فعل يبطل القديمة لا مجرد عرض، والجدولة تؤجل التسليم
 * ولا تُرسل فورًا، والصلاحية شرط على الخادم.
 */
final class ConsoleMessagingTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        config([
            'console.enabled' => true,
            'notifications.channels' => [
                'in_app' => [
                    'enabled' => true,
                    'gateway' => InAppChannelGateway::class,
                ],
            ],
            'notifications.quiet_hours.enabled' => false,
        ]);
        $this->withoutVite();
        $this->seed(NotificationTemplateSeeder::class);

        $organizationId = Fixtures::organizationId();
        $actor = User::query()->findOrFail(Fixtures::userId());
        $studentUser = User::query()->findOrFail(Fixtures::userId());
        $teacherUser = User::query()->findOrFail(Fixtures::userId());
        $course = Course::query()->findOrFail(Fixtures::courseId());

        $student = StudentProfile::query()->create([
            'organization_id' => $organizationId,
            'user_id' => $studentUser->id,
            'student_code' => 'S-MSG-'.Str::upper(Str::random(5)),
        ]);
        // المصنع المشترك يكتب employment_type غير صالح، فنكتب الملف هنا.
        $teacher = StaffProfile::query()->create([
            'organization_id' => $organizationId,
            'user_id' => $teacherUser->id,
            'staff_code' => 'T-MSG-'.Str::upper(Str::random(5)),
            'employment_type' => EmploymentType::Contractor,
            'gender' => StaffGender::Male,
            'hired_at' => '2026-01-01',
        ]);
        Enrollment::query()->create([
            'organization_id' => $organizationId,
            'student_profile_id' => $student->id,
            'program_id' => $course->level->program_id,
            'current_level_id' => $course->level_id,
            'status' => EnrollmentStatus::Active,
            'applied_at' => now('UTC')->subMonth(),
            'activated_at' => now('UTC')->subWeek(),
        ]);
        $schedule = Schedule::query()->create([
            'organization_id' => $organizationId,
            'student_profile_id' => $student->id,
            'course_id' => $course->id,
            'staff_profile_id' => $teacher->id,
            'session_type' => 'individual',
            'rrule' => 'FREQ=WEEKLY;BYDAY=MO',
            'start_time' => '10:00:00',
            'duration_minutes' => 60,
            'timezone' => 'Africa/Cairo',
            'starts_on' => now('UTC')->subWeek()->toDateString(),
            'materialized_until' => now('UTC')->addMonth()->toDateString(),
            'is_active' => true,
            'created_by' => $actor->id,
        ]);

        foreach (['admin.panel.access', 'notifications.outbox.create', 'student.view.any', 'staff.view.any', 'settings.manage'] as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->id === $actor->id);
        }
        $this->actingAs($actor);

        return compact('actor', 'studentUser', 'teacherUser', 'student', 'teacher', 'course', 'schedule');
    }

    public function test_free_text_message_from_a_profile_reaches_only_that_person(): void
    {
        $fixture = $this->fixture();

        $this->post('/manage/messages/students/'.$fixture['student']->id, [
            'kind' => 'free_text',
            'channel' => 'in_app',
            'subject' => 'تنبيه',
            'body' => 'مرحبًا {{name}}',
            'reason' => 'إبلاغ الطالب بموعد اللقاء',
            'request_id' => (string) Str::ulid(),
        ])->assertSessionHasNoErrors();

        $rows = NotificationOutbox::query()->where('event_name', 'notifications.manual')->get();

        $this->assertCount(1, $rows);
        $this->assertSame($fixture['studentUser']->id, $rows[0]->user_id);
        // المتغيرات تُستبدل قبل القيد؛ لا تصل الأقواس إلى المستلم.
        $this->assertStringContainsString($fixture['studentUser']->name, $rows[0]->body['ar']);
        $this->assertStringNotContainsString('{{name}}', $rows[0]->body['ar']);
    }

    public function test_sending_the_password_issues_a_new_one_and_invalidates_the_old(): void
    {
        $fixture = $this->fixture();
        $before = $fixture['studentUser']->password;

        $this->post('/manage/messages/students/'.$fixture['student']->id, [
            'kind' => 'credentials',
            'channel' => 'in_app',
            'fields' => ['username', 'password'],
            'reason' => 'الطالب فقد بيانات دخوله',
            'request_id' => (string) Str::ulid(),
        ])->assertSessionHasNoErrors();

        $user = $fixture['studentUser']->refresh();
        $body = NotificationOutbox::query()->where('event_name', 'notifications.manual')->sole()->body['ar'];

        $this->assertNotSame($before, $user->password);
        $this->assertTrue((bool) $user->must_change_password);
        // كلمة المرور المرسلة هي كلمة المرور الجديدة فعلًا، لا نصًا تجميليًا.
        $sent = $this->passwordLineFrom($body);
        $this->assertTrue(Hash::check($sent, $user->password));
    }

    public function test_audience_limits_who_receives_a_course_message(): void
    {
        $fixture = $this->fixture();

        $this->post('/manage/messages/audience', [
            'recipient_type' => 'course',
            'target_id' => $fixture['course']->id,
            'audience' => 'teacher',
            'channel' => 'in_app',
            'subject' => 'تنبيه المعلم',
            'body' => 'رسالة للمعلم فقط',
            'reason' => 'تنبيه تشغيلي للمعلم',
            'request_id' => (string) Str::ulid(),
        ])->assertSessionHasNoErrors();

        $recipients = NotificationOutbox::query()
            ->where('event_name', 'notifications.manual')
            ->pluck('user_id')
            ->all();

        $this->assertSame([$fixture['teacherUser']->id], $recipients);

        $this->post('/manage/messages/audience', [
            'recipient_type' => 'course',
            'target_id' => $fixture['course']->id,
            'audience' => 'students',
            'channel' => 'in_app',
            'subject' => 'تنبيه الطلاب',
            'body' => 'رسالة للطلاب فقط',
            'reason' => 'تنبيه تشغيلي للطلاب',
            'request_id' => (string) Str::ulid(),
        ])->assertSessionHasNoErrors();

        $studentRecipients = NotificationOutbox::query()
            ->where('event_name', 'notifications.manual')
            ->whereNot('user_id', $fixture['teacherUser']->id)
            ->pluck('user_id')
            ->all();

        $this->assertSame([$fixture['studentUser']->id], $studentRecipients);
    }

    public function test_a_scheduled_message_waits_for_its_time_instead_of_going_out_now(): void
    {
        $fixture = $this->fixture();
        $sendAt = CarbonImmutable::now('UTC')->addDays(2);

        $this->post('/manage/messages/students/'.$fixture['student']->id, [
            'kind' => 'free_text',
            'channel' => 'in_app',
            'subject' => 'تذكير مجدول',
            'body' => 'رسالة مجدولة',
            'reason' => 'تذكير قبل بداية الفصل الدراسي',
            'request_id' => (string) Str::ulid(),
            'scheduled_for' => $sendAt->toIso8601String(),
        ])->assertSessionHasNoErrors();

        $row = NotificationOutbox::query()->where('event_name', 'notifications.manual')->sole();

        $this->assertTrue($row->scheduled_for->greaterThan(CarbonImmutable::now('UTC')->addDay()));
    }

    public function test_sending_requires_the_dispatch_permission(): void
    {
        $fixture = $this->fixture();
        Gate::define('notifications.outbox.create', fn (): bool => false);

        $this->post('/manage/messages/students/'.$fixture['student']->id, [
            'kind' => 'free_text',
            'channel' => 'in_app',
            'subject' => 'محاولة',
            'body' => 'رسالة',
            'reason' => 'محاولة بلا صلاحية',
            'request_id' => (string) Str::ulid(),
        ])->assertForbidden();

        $this->assertSame(0, NotificationOutbox::query()->count());
    }

    public function test_a_refused_send_never_invalidates_the_current_password(): void
    {
        $fixture = $this->fixture();
        $before = $fixture['studentUser']->password;
        $payload = [
            'kind' => 'credentials',
            'channel' => 'in_app',
            'fields' => ['username', 'password'],
            'reason' => 'إعادة إرسال بنفس المعرّف',
            'request_id' => (string) Str::ulid(),
        ];

        $this->post('/manage/messages/students/'.$fixture['student']->id, $payload)
            ->assertSessionHasNoErrors();
        $issued = $fixture['studentUser']->refresh()->password;

        // نفس المعرّف مرة ثانية: الطلب مُنفَّذ سلفًا فلا رسالة جديدة — ولا يجوز
        // أن تُولَّد كلمة مرور ثالثة تبطل التي وصلت الطالب فعلًا في الرسالة.
        $this->post('/manage/messages/students/'.$fixture['student']->id, $payload)
            ->assertSessionHasNoErrors();

        $this->assertNotSame($before, $issued);
        $this->assertSame($issued, $fixture['studentUser']->refresh()->password);
        $this->assertSame(1, NotificationOutbox::query()->where('event_name', 'notifications.manual')->count());
    }

    public function test_a_disabled_channel_is_refused_before_the_password_is_touched(): void
    {
        $fixture = $this->fixture();
        $before = $fixture['studentUser']->password;

        $this->post('/manage/messages/students/'.$fixture['student']->id, [
            'kind' => 'credentials',
            'channel' => 'whatsapp',
            'fields' => ['username', 'password'],
            'reason' => 'محاولة على قناة مطفأة',
            'request_id' => (string) Str::ulid(),
        ])->assertSessionHasErrors('message');

        $this->assertSame($before, $fixture['studentUser']->refresh()->password);
        $this->assertFalse((bool) $fixture['studentUser']->refresh()->must_change_password);
        $this->assertSame(0, NotificationOutbox::query()->count());
    }

    public function test_a_past_send_time_is_refused_in_the_sender_timezone(): void
    {
        $fixture = $this->fixture();
        $fixture['actor']->forceFill(['timezone' => 'Africa/Cairo'])->save();

        $this->post('/manage/messages/students/'.$fixture['student']->id, [
            'kind' => 'free_text',
            'channel' => 'in_app',
            'subject' => 'رسالة',
            'body' => 'نص',
            'reason' => 'موعد ماضٍ بتوقيت المرسل',
            'request_id' => (string) Str::ulid(),
            'scheduled_for' => CarbonImmutable::now('Africa/Cairo')->subHour()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('message');

        $this->assertSame(0, NotificationOutbox::query()->count());
    }

    public function test_the_send_time_is_read_in_the_sender_timezone(): void
    {
        $fixture = $this->fixture();
        $fixture['actor']->forceFill(['timezone' => 'Africa/Cairo'])->save();
        $local = CarbonImmutable::now('Africa/Cairo')->addDay()->setTime(18, 0);

        $this->post('/manage/messages/students/'.$fixture['student']->id, [
            'kind' => 'free_text',
            'channel' => 'in_app',
            'subject' => 'رسالة',
            'body' => 'نص',
            'reason' => 'جدولة بتوقيت الإدارة',
            'request_id' => (string) Str::ulid(),
            'scheduled_for' => $local->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();

        $row = NotificationOutbox::query()->where('event_name', 'notifications.manual')->sole();

        // السادسة مساءً بتوقيت القاهرة، لا السادسة بتوقيت التطبيق.
        $this->assertSame(
            $local->utc()->format('Y-m-d H:i'),
            $row->scheduled_for->utc()->format('Y-m-d H:i'),
        );
    }

    private function passwordLineFrom(string $body): string
    {
        foreach (explode(PHP_EOL, $body) as $line) {
            $label = (string) __('console_messaging.credentials.fields.password').': ';

            if (str_starts_with($line, $label)) {
                return trim(substr($line, strlen($label)));
            }
        }

        return '';
    }
}
