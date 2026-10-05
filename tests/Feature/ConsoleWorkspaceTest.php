<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Reporting\Application\Services\OperationalReportCriteriaFactory;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Students\Domain\Models\StudentProfile;
use Tests\TestCase;

final class ConsoleWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $permissions = [
        'admin.panel.access', 'report.view', 'report.export', 'student.view.any',
        'staff.view.any', 'organizations.view', 'organizations.update',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['console.enabled' => true]);
        $this->withoutVite();
        foreach ($this->permissions as $ability) {
            Gate::define($ability, fn (): bool => in_array($ability, $this->permissions, true));
        }
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        $this->travelBack();
        parent::tearDown();
    }

    public function test_workspace_requires_login_permission_and_an_active_account(): void
    {
        [, $actor] = $this->context();
        $this->get('/manage')->assertRedirect(route('login'));
        $this->permissions = [];
        $this->actingAs($actor)->get('/manage')->assertForbidden();
        $this->permissions = ['admin.panel.access'];
        $this->get('/manage/notifications')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Console/Notifications'));
        $this->get('/manage')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Console/Workspace')->where('summary', null));
        $actor->update(['status' => UserStatus::Suspended]);
        $this->get('/manage')->assertForbidden();
    }

    public function test_console_uses_arabic_without_overwriting_the_account_language(): void
    {
        [, $actor] = $this->context();
        $actor->update(['locale' => 'en']);
        $this->actingAs($actor)->withSession(['locale' => 'fr'])->get('/manage')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'ar')->where('direction', 'rtl')->where('supportedLocales', ['ar']));
        self::assertSame('en', $actor->fresh()->locale);
    }

    public function test_feature_flag_closes_all_new_entry_points_without_changing_existing_routes(): void
    {
        [, $actor] = $this->context();
        config(['console.enabled' => false]);
        $this->actingAs($actor)->get('/manage')->assertNotFound();
        $this->get('/manage/reports')->assertNotFound();
        $this->get('/manage/notifications')->assertNotFound();
        $this->get('/manage/settings')->assertNotFound();
        $this->put('/manage/settings', [])->assertNotFound();
        $this->get('/manage/students')->assertNotFound();
        $this->get('/learn/student')->assertNotFound();
        self::assertNotNull(app('router')->getRoutes()->getByName('filament.admin.pages.dashboard'));
    }

    public function test_report_reads_real_sessions_for_the_local_day_and_one_organization(): void
    {
        [$organization, $actor, $course, $teacher] = $this->context();
        $included = $this->createSession($organization, $course, $teacher, '2026-09-05T23:30:00Z');
        $this->createSession($organization, $course, $teacher, '2026-09-05T20:30:00Z');
        [$foreignOrganization, , $foreignCourse, $foreignTeacher] = $this->context();
        $this->createSession($foreignOrganization, $foreignCourse, $foreignTeacher, '2026-09-05T23:30:00Z');
        $this->actingAs($actor)->get('/manage/reports?preset=custom&from=2026-09-06&until=2026-09-06')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Console/Reports')->where('timezone', 'Africa/Cairo')
            ->where('summary.total', 1)->has('rows', 1)->where('rows.0.id', (string) $included->id)
            ->where('rows.0.group', '')
            ->where('overview.groups.0.label', fn (string $label): bool => !str_contains($label, 'غير معروفة'))
            ->has('overview.days', 1)->where('overview.days.0.date', '2026-09-06')->where('overview.days.0.planned', 1)
            ->has('overview.groups', 1)->where('overview.groups.0.planned', 1));
    }

    public function test_report_week_uses_school_start_day_and_user_timezone(): void
    {
        [$organization, $actor] = $this->context();
        $organization->update(['week_starts_on' => 'monday', 'default_timezone' => 'Asia/Tokyo']);
        $actor->update(['timezone' => 'Africa/Cairo']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-06T22:30:00Z'));
        $factory = app(OperationalReportCriteriaFactory::class);
        $criteria = $factory->fromInput(['preset' => 'this_week'], $actor);
        self::assertSame('Africa/Cairo', $criteria->timezone);
        self::assertSame('2026-09-07', $criteria->fromDate);
        self::assertSame('2026-09-13', $criteria->untilDate);
        self::assertSame('2026-09-06T21:00:00+00:00', $criteria->fromUtc->toIso8601String());
        $organization->update(['week_starts_on' => 'sunday']);
        self::assertSame('2026-09-06', $factory->fromInput(['preset' => 'this_week'], $actor)->fromDate);
    }

    public function test_report_pagination_preserves_array_filters_and_original_teacher(): void
    {
        [$organization, $actor, $course, $teacher] = $this->context();
        config(['console.report_per_page' => 1]);
        foreach (['08:00', '09:00', '10:00'] as $time) {
            $this->createSession($organization, $course, $teacher, '2026-09-06T'.$time.':00Z', [
                'substitute_for_staff_id' => $teacher->id,
            ]);
        }
        $filters = [
            'preset' => 'custom', 'from' => '2026-09-06', 'until' => '2026-09-06',
            'statuses' => ['scheduled'], 'session_types' => ['group'],
            'original_staff_profile_id' => (string) $teacher->id,
        ];
        $response = $this->actingAs($actor)->get('/manage/reports?'.http_build_query($filters))
            ->assertOk();
        $next = data_get($response->viewData('page'), 'props.pagination.nextUrl');
        self::assertIsString($next);
        parse_str((string) parse_url($next, PHP_URL_QUERY), $query);
        self::assertSame(['scheduled'], $query['statuses']);
        self::assertSame(['group'], $query['session_types']);
        self::assertSame((string) $teacher->id, $query['original_staff_profile_id']);
        self::assertSame('2', $query['page']);
        $this->get($next)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('pagination.current', 2)->where('pagination.total', 3)
            ->where('filters.statuses', ['scheduled'])->where('filters.session_types', ['group'])
            ->where('filters.original_staff_profile_id', (string) $teacher->id)
            ->where('overview.days.0.planned', 3)->where('overview.groups.0.planned', 3));
    }

    public function test_invalid_get_filters_redirect_to_a_clean_report_instead_of_a_loop(): void
    {
        [, $actor] = $this->context();
        $invalid = '/manage/reports?statuses=scheduled';
        $this->actingAs($actor)->from($invalid)->get($invalid)
            ->assertRedirect(route('console.reports'))->assertSessionHasErrors('statuses');
        $this->from('/manage/reports?preset=custom')->get('/manage/reports?preset=custom')
            ->assertRedirect(route('console.reports'))->assertSessionHasErrors(['from', 'until']);
        $this->get('/manage/reports?preset=custom&from=2020-01-01&until=2026-09-06')
            ->assertRedirect(route('console.reports'))->assertSessionHasErrors('period');
    }

    public function test_report_and_settings_read_permissions_do_not_grant_export_or_write(): void
    {
        [, $actor] = $this->context();
        $this->permissions = ['admin.panel.access', 'student.view.any', 'report.view', 'organizations.view'];
        $this->actingAs($actor)->get('/manage/reports')->assertOk()->assertInertia(fn (Assert $page) => $page->where('exportUrl', null));
        $this->get('/manage/reports/pdf')->assertForbidden();
        $this->get('/manage/settings')->assertOk()->assertInertia(fn (Assert $page) => $page->where('canUpdate', false));
        $this->put('/manage/settings', [])->assertForbidden();
        $this->permissions = ['admin.panel.access'];
        $this->get('/manage/reports')->assertForbidden();
        $this->get('/manage/settings')->assertForbidden();
    }

    public function test_teacher_report_filters_do_not_disclose_people_outside_their_sessions(): void
    {
        [$organization, $actor, $course, $teacher] = $this->context();
        $studentUser = User::factory()->create(['organization_id' => $organization->id, 'name' => 'اسم طالب غير مسند']);
        $student = StudentProfile::factory()->create(['organization_id' => $organization->id, 'user_id' => $studentUser->id]);
        $otherUser = User::factory()->create(['organization_id' => $organization->id, 'name' => 'اسم معلم غير مرتبط']);
        $otherTeacher = StaffProfile::query()->create(['organization_id' => $organization->id, 'user_id' => $otherUser->id, 'staff_code' => 'T'.$otherUser->id, 'employment_type' => EmploymentType::Contractor]);
        $this->createSession($organization, $course, $teacher, '2026-09-06T08:00:00Z');
        $this->permissions = ['admin.panel.access', 'report.view'];
        $response = $this->actingAs($actor)->get('/manage/reports?'.http_build_query([
            'preset' => 'custom', 'from' => '2026-09-06', 'until' => '2026-09-06',
            'student_profile_id' => $student->id, 'original_staff_profile_id' => $otherTeacher->id,
        ]))->assertOk();
        $options = data_get($response->viewData('page'), 'props.options');
        self::assertArrayNotHasKey($student->id, $options['students']);
        self::assertArrayNotHasKey($otherTeacher->id, $options['teachers']);
        self::assertArrayHasKey($teacher->id, $options['teachers']);
        self::assertStringNotContainsString($studentUser->name, json_encode($options, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString($otherUser->name, json_encode($options, JSON_THROW_ON_ERROR));
    }

    public function test_settings_are_scoped_audited_and_never_expose_secrets_or_reschedule_sessions(): void
    {
        [$organization, $actor, $course, $teacher] = $this->context();
        [$foreignOrganization] = $this->context();
        $session = $this->createSession($organization, $course, $teacher, '2026-09-06T08:00:00Z');
        $start = $session->scheduled_start->toISOString();
        config(['virtual-classroom.bigbluebutton.secret' => 'never-expose-console-secret']);
        $organization->update(['name' => ['ar' => 'المؤسسة', 'en' => 'Existing English name', 'fr' => 'Nom existant'], 'default_locale' => 'en']);
        $this->actingAs($actor);
        $response = $this->get('/manage/settings')->assertOk()->assertDontSee('never-expose-console-secret');
        $school = data_get($response->viewData('page'), 'props.school');
        self::assertSame(['ar'], array_keys($school['name']));
        self::assertArrayNotHasKey('default_locale', $school);
        $this->put('/manage/settings', [
            'name' => ['ar' => 'اسم المؤسسة الجديد'],
            'default_timezone' => 'Europe/London', 'default_locale' => 'ar', 'version' => $school['version'],
            'organization_id' => $foreignOrganization->id, 'week_starts_on' => 'monday',
            'feature_overrides' => ['payroll' => false],
        ])->assertSessionHasNoErrors()->assertRedirect(route('console.settings'));
        $organization->refresh();
        self::assertSame('Europe/London', $organization->default_timezone);
        self::assertSame('monday', $organization->week_starts_on);
        self::assertSame('Existing English name', $organization->name['en']);
        self::assertSame('Nom existant', $organization->name['fr']);
        self::assertSame('اسم المؤسسة الجديد', $organization->name['ar']);
        self::assertSame('en', $organization->default_locale);
        self::assertSame('Africa/Cairo', $foreignOrganization->fresh()->default_timezone);
        self::assertSame($start, $session->fresh()->scheduled_start->toISOString());
        self::assertSame('Africa/Cairo', $actor->fresh()->timezone);
        self::assertTrue(DB::table('audit_log')->where([
            'organization_id' => $organization->id,
            'action' => 'console.school_settings.updated',
            'actor_id' => $actor->id,
        ])->exists());
    }

    public function test_settings_reject_a_stale_version_even_when_updates_share_the_same_second(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-06T08:00:00Z'));
        CarbonImmutable::setTestNow('2026-09-06T08:00:00Z');
        [$organization, $actor] = $this->context();
        $this->actingAs($actor);
        $school = data_get($this->get('/manage/settings')->assertOk()->viewData('page'), 'props.school');
        $updatedAt = $organization->updated_at->toISOString();
        $data = ['name' => $school['name'], 'default_timezone' => 'Europe/London', 'default_locale' => 'ar', 'version' => $school['version']];
        $this->put('/manage/settings', $data)->assertSessionHasNoErrors();
        self::assertSame($updatedAt, $organization->fresh()->updated_at->toISOString());
        $this->put('/manage/settings', [...$data, 'default_timezone' => 'Asia/Tokyo'])->assertSessionHasErrors('version');
        self::assertSame('Europe/London', $organization->fresh()->default_timezone);
        self::assertSame(1, DB::table('audit_log')->where('action', 'console.school_settings.updated')->count());
    }

    /** @return array{Organization, User, Course, StaffProfile} */
    private function context(): array
    {
        $organization = Organization::factory()->create(['default_timezone' => 'Africa/Cairo']);
        $actor = User::factory()->inOrganization((string) $organization->id)->create(['timezone' => 'Africa/Cairo']);
        $teacher = StaffProfile::query()->create(['organization_id' => $organization->id, 'user_id' => $actor->id, 'staff_code' => 'T'.$actor->id, 'employment_type' => EmploymentType::Contractor]);
        $program = Program::factory()->create(['organization_id' => $organization->id]);
        $level = Level::factory()->create(['program_id' => $program->id]);
        $course = Course::factory()->create(['organization_id' => $organization->id, 'level_id' => $level->id]);

        return [$organization, $actor, $course, $teacher];
    }

    /** @param array<string, mixed> $attributes */
    private function createSession(Organization $organization, Course $course, StaffProfile $teacher, string $start, array $attributes = []): Session
    {
        return Session::factory()->create([
            'organization_id' => $organization->id, 'course_id' => $course->id, 'staff_profile_id' => $teacher->id,
            'status' => SessionStatus::Scheduled, 'session_type' => 'group',
            'scheduled_start' => CarbonImmutable::parse($start),
            'scheduled_end' => CarbonImmutable::parse($start)->addMinutes(45),
            ...$attributes,
        ]);
    }
}
