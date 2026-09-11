<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Modules\Identity\Domain\Models\User;
use Modules\VirtualClassroom\Domain\Contracts\VirtualClassroomProvider;
use Modules\VirtualClassroom\Domain\Enums\ClassroomStatus;
use Modules\VirtualClassroom\Domain\Models\Classroom;
use Tests\TestCase;

/**
 * وجهة العودة بعد الفصل المباشر.
 *
 * ما يثبته هذا الملف: رابط الدخول يحمل logoutURL مختلفًا باختلاف الدور، فيعود
 * المعلم إلى قسم تقرير الحصة والطالب إلى واجهته بدل صفحة المزوّد، وأن الداخل
 * من المسارات السابقة يعود إليها لا إلى بوابة أخرى.
 */
final class ClassroomReturnDestinationTest extends TestCase
{
    use CreatesSessionParticipant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'console.enabled' => true,
            'virtual-classroom.default' => 'bigbluebutton',
            'virtual-classroom.providers.bigbluebutton.base_url' => 'https://bbb.test/bigbluebutton/',
            'virtual-classroom.providers.bigbluebutton.secret' => 'api-secret',
        ]);
        app()->forgetInstance(VirtualClassroomProvider::class);
        Gate::define('session.join', static fn (): bool => true);
        Http::fake([
            '*isMeetingRunning*' => Http::response(
                '<response><returncode>SUCCESS</returncode><running>true</running></response>',
            ),
        ]);
    }

    public function test_teacher_returns_to_the_lesson_report_after_leaving_the_classroom(): void
    {
        $context = $this->joinableSession();
        $session = $context['session_id'];

        $response = $this->actingAs($context['teacher'])->get('/learn/teacher/sessions/'.$session.'/join');

        $response->assertRedirect();
        $this->assertSame(
            url('/learn/teacher/sessions/'.$session),
            $this->returnDestination((string) $response->headers->get('Location')),
        );
    }

    public function test_student_returns_to_their_own_portal_after_leaving_the_classroom(): void
    {
        $context = $this->joinableSession();

        $response = $this->actingAs($context['student'])
            ->get('/learn/student/sessions/'.$context['session_id'].'/join');

        $response->assertRedirect();
        $this->assertSame(
            url('/learn/student'),
            $this->returnDestination((string) $response->headers->get('Location')),
        );
    }

    public function test_participants_entering_from_the_earlier_routes_are_returned_inside_them(): void
    {
        $context = $this->joinableSession();
        $session = $context['session_id'];

        $teacher = $this->actingAs($context['teacher'])->get('/teacher/sessions/'.$session.'/join');
        $teacher->assertRedirect();
        $student = $this->actingAs($context['student'])->get('/student/sessions/'.$session.'/join');
        $student->assertRedirect();

        $this->assertSame(
            url('/teacher/sessions/'.$session),
            $this->returnDestination((string) $teacher->headers->get('Location')),
        );
        $this->assertSame(
            url('/student'),
            $this->returnDestination((string) $student->headers->get('Location')),
        );
    }

    public function test_the_manual_student_link_keeps_the_provider_default_because_it_has_no_portal(): void
    {
        $context = $this->joinableSession();
        $participant = DB::table('session_participants')
            ->where('session_id', $context['session_id'])
            ->first();

        // الداخل من هذا الرابط بلا جلسة على المنصة، فأي وجهة داخلية تعني
        // شاشة تسجيل دخول بدل صفحة المزوّد. نثبّت غياب الوجهة عمدًا.
        $response = $this->get(URL::temporarySignedRoute(
            'classroom.student-link',
            CarbonImmutable::now('UTC')->addHour(),
            ['session' => $context['session_id'], 'participant' => $participant->id],
        ));

        $response->assertRedirect();
        $this->assertNull($this->returnDestination((string) $response->headers->get('Location')));
    }

    /** @return array{session_id: string, teacher: User, student: User} */
    private function joinableSession(): array
    {
        $participant = DB::table('session_participants')
            ->where('id', $this->createSessionParticipant())
            ->first();
        $session = DB::table('sessions')->where('id', $participant->session_id)->first();

        $start = CarbonImmutable::now('UTC')->addMinutes(5);
        DB::table('sessions')->where('id', $session->id)->update([
            'status' => 'scheduled',
            'scheduled_start' => $start,
            'scheduled_end' => $start->addHour(),
        ]);

        // فصل مُهيَّأ سلفًا فلا يحتاج الدخول إنشاءً جديدًا عند المزوّد.
        // الإنشاء عبر الموديل لا عبر DB لأن أسرار الدور حقول مشفّرة.
        Classroom::query()->create([
            'session_id' => $session->id,
            'provider' => 'bigbluebutton',
            'external_id' => 'SES-'.$session->id,
            'moderator_secret' => 'moderator-secret',
            'attendee_secret' => 'viewer-secret',
            'created_remote_at' => now(),
            'status' => ClassroomStatus::Provisioned,
        ]);

        return [
            'session_id' => (string) $session->id,
            'teacher' => User::query()->findOrFail(
                DB::table('staff_profiles')->where('id', $session->staff_profile_id)->value('user_id'),
            ),
            'student' => User::query()->findOrFail(
                DB::table('student_profiles')->where('id', $participant->student_profile_id)->value('user_id'),
            ),
        ];
    }

    private function returnDestination(string $location): ?string
    {
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return isset($query['logoutURL']) ? (string) $query['logoutURL'] : null;
    }
}
