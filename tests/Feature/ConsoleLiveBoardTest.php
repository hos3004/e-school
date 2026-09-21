<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Academics\Domain\Models\Course;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Students\Domain\Models\StudentProfile;
use Tests\TestCase;

/**
 * شاشة المتابعة تُشتق حالتها من الساعة وحضور الغرفة، لا من حالة الحصة وحدها.
 */
final class ConsoleLiveBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        /*
         * الشاشة تعرض «اليوم» بتوقيت المدرسة، فتشغيل الاختبار بعد منتصف الليل
         * المحلي كان يُخرج حصص المساء من النافذة. تثبيت الساعة عند منتصف نهار
         * القاهرة يجعل الإزاحات في الاختبار داخل اليوم المحلي دائمًا.
         */
        $this->travelTo(CarbonImmutable::create(2026, 9, 20, 9, 0, 0, 'UTC'));
        config(['console.enabled' => true]);
        $this->seed(AccessControlSeeder::class);
        app(PermissionGateRegistrar::class)->register();
    }

    public function test_permissions_and_organization_scope_guard_the_board(): void
    {
        [$org] = $this->context();
        $this->get('/manage/live')->assertRedirect(route('login'));
        $this->actingAs($this->actor($org, ['admin.panel.access', 'session.view']))->get('/manage/live')->assertForbidden();
        $this->actingAs($this->actor($org, ['admin.panel.access', 'student.view.any']))->get('/manage/live')->assertForbidden();
        $this->actingAs($this->watcher($org))->get('/manage/live')->assertOk();
    }

    public function test_each_live_state_is_derived_from_the_clock_and_who_is_in_the_room(): void
    {
        [$org, $staff, $teacherUser, $course] = $this->context();
        $watcher = $this->watcher($org);

        /*
         * كل حصة متزامنة بمعلمها: قاعدة البيانات تمنع حجز المعلم مرتين في
         * وقت متداخل (`sessions_no_teacher_double_booking`).
         */
        [$staffB, $teacherB] = $this->teacher($org);
        [$staffC, $teacherC] = $this->teacher($org);
        [$staffD, $teacherD] = $this->teacher($org);

        // جارية الآن: بدأت قبل نصف ساعة وتنتهي بعد نصفها.
        $bothIn = $this->lesson($org, $staff, $course, -30, 30);
        $this->join($bothIn, $teacherUser->id);
        $this->student($org, $bothIn, inside: true);

        $teacherOnly = $this->lesson($org, $staffB, $course, -30, 30);
        $this->join($teacherOnly, $teacherB->id);
        $this->student($org, $teacherOnly, inside: false);

        $studentOnly = $this->lesson($org, $staffC, $course, -30, 30);
        $this->student($org, $studentOnly, inside: true);

        $nobody = $this->lesson($org, $staffD, $course, -30, 30);
        $this->student($org, $nobody, inside: false);

        // قادمة، ومنتهية بلا قرار، ومنتهية مقفلة.
        $upcoming = $this->lesson($org, $staff, $course, 60, 120);
        $unresolved = $this->lesson($org, $staff, $course, -180, -120);
        $closed = $this->lesson($org, $staff, $course, -300, -240, SessionStatus::Completed);

        $expected = [
            $bothIn->id => 'running_ok',
            $teacherOnly->id => 'running_no_student',
            $studentOnly->id => 'running_no_teacher',
            $nobody->id => 'running_nobody',
            $upcoming->id => 'upcoming',
            $unresolved->id => 'ended_unresolved',
            $closed->id => 'ended',
        ];

        $this->actingAs($watcher)->get('/manage/live')->assertOk()
            ->assertInertia(function (Assert $page) use ($expected): void {
                $page->component('Console/LiveBoard')->has('rows', 7);
                $states = collect($page->toArray()['props']['rows'])->pluck('state', 'id');
                foreach ($expected as $id => $state) {
                    expect($states[$id])->toBe($state);
                }
            });
    }

    public function test_a_teacher_who_left_the_room_is_no_longer_counted_present(): void
    {
        [$org, $staff, $teacherUser, $course] = $this->context();
        $session = $this->lesson($org, $staff, $course, -20, 40);
        $this->student($org, $session, inside: true);
        $this->join($session, $teacherUser->id, minutesAgo: 18);

        $this->actingAs($this->watcher($org))->get('/manage/live')
            ->assertInertia(fn (Assert $page): Assert => $page->where('rows.0.state', 'running_ok')
                ->where('rows.0.teacherIn', true));

        $this->leave($session, $teacherUser->id, minutesAgo: 2);

        /*
         * الخروج يغيّر الإجابة فورًا: السؤال «هل هو في الغرفة الآن؟» لا
         * «هل حضر خلال الموعد؟» — وهو الفارق الذي يجعل الشاشة تنبيهًا لا تقريرًا.
         */
        $this->get('/manage/live')
            ->assertInertia(fn (Assert $page): Assert => $page->where('rows.0.state', 'running_no_teacher')
                ->where('rows.0.teacherIn', false)->where('counts.running_no_teacher', 1));
    }

    public function test_a_finished_session_separates_what_the_teacher_delivered_from_what_nobody_knows(): void
    {
        [$org, $staff, $teacherUser, $course] = $this->context();

        // فتح المعلم الغرفة: دليل من المنصة نفسها على أن الحصة أُدّيت.
        $opened = $this->lesson($org, $staff, $course, -300, -240);
        DB::table('sessions')->where('id', $opened->id)
            ->update(['actual_start' => $opened->scheduled_start]);

        // لم تُفتح من المنصة، لكن المعلم أقرّ بأدائها في تقرير.
        $reported = $this->lesson($org, $staff, $course, -230, -180);
        DB::table('session_reports')->insert(['id' => (string) Str::ulid(), 'session_id' => $reported->id,
            'staff_profile_id' => $staff, 'topics_covered' => 'مراجعة.', 'submitted_at' => now(),
            'is_late' => false, 'created_at' => now(), 'updated_at' => now()]);

        // لا دخول ولا تقرير: لا يُعرف إن كانت دُرِّست خارج المنصة أم لم تُقَم.
        $silent = $this->lesson($org, $staff, $course, -170, -120);

        // قرار موثَّق بعذر مقبول: حالة نهائية، فلا تُعرض كأنها تنتظر قرارًا.
        $excused = $this->lesson($org, $staff, $course, -110, -60, SessionStatus::Excused);

        $this->actingAs($this->watcher($org))->get('/manage/live')->assertOk()
            ->assertInertia(function (Assert $page) use ($opened, $reported, $silent, $excused): void {
                $states = collect($page->toArray()['props']['rows'])->pluck('state', 'id');

                expect($states[$opened->id])->toBe('due')
                    ->and($states[$reported->id])->toBe('due')
                    ->and($states[$silent->id])->toBe('ended_unresolved')
                    ->and($states[$excused->id])->toBe('ended');

                $page->where('counts.due', 2)->where('counts.ended_unresolved', 1);
            });
    }

    public function test_the_board_shows_only_today_and_only_this_organization(): void
    {
        [$org, $staff, $teacherUser, $course] = $this->context();
        [$otherOrg, $otherStaff, $otherTeacher, $otherCourse] = $this->context();
        $today = $this->lesson($org, $staff, $course, -10, 50);
        $this->lesson($org, $staff, $course, -60 * 30, -60 * 29); // أمس
        $this->lesson($otherOrg, $otherStaff, $otherCourse, -10, 50);

        $this->actingAs($this->watcher($org))->get('/manage/live')
            ->assertInertia(fn (Assert $page): Assert => $page->has('rows', 1)->where('rows.0.id', $today->id));
    }

    /** @return array{string, User} */
    private function teacher(string $organizationId): array
    {
        $user = User::factory()->inOrganization($organizationId)->create(['name' => 'معلم', 'locale' => 'ar']);
        $staff = (string) Str::ulid();
        DB::table('staff_profiles')->insert(['id' => $staff, 'organization_id' => $organizationId,
            'user_id' => $user->id, 'staff_code' => 'L-'.Str::random(8), 'employment_type' => 'part_time',
            'created_at' => now(), 'updated_at' => now()]);

        return [$staff, $user];
    }

    /** @return array{string, string, User, Course} */
    private function context(): array
    {
        $org = (string) Organization::factory()->create()->id;
        [$staff, $teacherUser] = $this->teacher($org);
        $course = Course::factory()->create(['organization_id' => $org, 'name' => ['ar' => 'دورة القرآن']]);

        return [$org, $staff, $teacherUser, $course];
    }

    /** @param list<string> $permissions */
    private function actor(string $org, array $permissions): User
    {
        $actor = User::factory()->inOrganization($org)->create(['locale' => 'ar', 'timezone' => 'Africa/Cairo']);
        foreach ($permissions as $name) {
            $permission = Permission::query()->where('name', $name)->sole();
            ModelHasPermission::query()->create(['permission_id' => $permission->id,
                'model_type' => $actor->getMorphClass(), 'model_id' => $actor->id]);
        }

        return $actor;
    }

    private function watcher(string $org): User
    {
        return $this->actor($org, ['admin.panel.access', 'session.view', 'student.view.any', 'session.finalize']);
    }

    private function lesson(string $org, string $staff, Course $course, int $startsInMinutes, int $endsInMinutes,
        SessionStatus $status = SessionStatus::Scheduled): Session
    {
        $now = CarbonImmutable::now('UTC');

        return Session::query()->create(['organization_id' => $org, 'course_id' => $course->id,
            'staff_profile_id' => $staff, 'original_teacher_id' => $staff, 'session_type' => 'regular',
            'status' => $status, 'scheduled_start' => $now->addMinutes($startsInMinutes),
            'scheduled_end' => $now->addMinutes($endsInMinutes), 'title' => ['ar' => 'حصة']]);
    }

    private function student(string $org, Session $session, bool $inside): void
    {
        $student = StudentProfile::factory()->create(['organization_id' => $org]);
        $enrollment = (string) Str::ulid();
        DB::table('enrollments')->insert(['id' => $enrollment, 'organization_id' => $org,
            'student_profile_id' => $student->id,
            'program_id' => DB::table('levels')->where('id', DB::table('courses')->where('id', $session->course_id)->value('level_id'))->value('program_id'),
            'status' => 'active', 'applied_at' => now(), 'activated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('session_participants')->insert(['id' => (string) Str::ulid(), 'session_id' => $session->id,
            'student_profile_id' => $student->id, 'enrollment_id' => $enrollment,
            'join_url_token' => Str::random(64), 'invited_at' => now(),
            'first_joined_at' => $inside ? now() : null,
            'current_joined_at' => $inside ? now() : null,
            'created_at' => now()]);
    }

    private function classroom(Session $session): string
    {
        $existing = DB::table('classrooms')->where('session_id', $session->id)->value('id');
        if (is_string($existing)) {
            return $existing;
        }
        $id = (string) Str::ulid();
        DB::table('classrooms')->insert(['id' => $id, 'session_id' => $session->id, 'provider' => 'bbb',
            'status' => 'ready', 'health_status' => 'unknown', 'max_concurrent_participants' => 0,
            'link_generation' => 1, 'provision_attempts' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function join(Session $session, string $userId, int $minutesAgo = 10): void
    {
        $this->event($session, $userId, 'participant_joined', $minutesAgo);
    }

    private function leave(Session $session, string $userId, int $minutesAgo = 1): void
    {
        $this->event($session, $userId, 'participant_left', $minutesAgo);
    }

    private function event(Session $session, string $userId, string $type, int $minutesAgo): void
    {
        DB::table('classroom_events')->insert(['id' => (string) Str::ulid(),
            'classroom_id' => $this->classroom($session), 'event_type' => $type, 'user_id' => $userId,
            'occurred_at' => CarbonImmutable::now('UTC')->subMinutes($minutesAgo),
            'idempotency_key' => (string) Str::ulid(), 'created_at' => now()]);
    }
}
