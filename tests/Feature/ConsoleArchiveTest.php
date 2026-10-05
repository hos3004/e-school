<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\Groups\Domain\Models\Group;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Tests\TestCase;

/**
 * صفحة الأرشيف ومسارَي الإقفال وإعادة الفتح.
 *
 * ما يثبته هذا الاختبار تحديدًا: الإقفال يخرج العنصر من الواجهة ولا يحذفه،
 * والصلاحية تُفحص على الخادم لا بإخفاء زر، والمؤسسة الأخرى لا تصل إلى سجلّ
 * ليس لها.
 */
final class ConsoleArchiveTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $permissions = [
        'admin.panel.access', 'program.manage', 'course.manage', 'group.manage', 'group.view',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['console.enabled' => true]);
        $this->withoutVite();
        $this->seed([AccessControlSeeder::class]);
        $this->grant($this->permissions);
    }

    public function test_archive_page_lists_closed_items_with_their_frozen_summary(): void
    {
        [$organization, $actor] = $this->context();
        $program = Program::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($actor)
            ->post('/manage/archive/program/'.$program->id.'/close', ['reason' => 'انتهى الموسم الصيفي'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->actingAs($actor)->get('/manage/archive')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Console/Archive')
                ->where('sections.programs.0.id', (string) $program->id)
                ->where('sections.programs.0.reason', 'انتهى الموسم الصيفي')
                ->where('sections.programs.0.closedBy', $actor->name)
                ->where('sections.programs.0.summary.kind', 'program'));
    }

    public function test_closing_hides_from_the_working_view_without_deleting_anything(): void
    {
        [$organization, $actor] = $this->context();
        $group = Group::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($actor)
            ->post('/manage/archive/group/'.$group->id.'/close', ['reason' => 'أنهت مدتها'])
            ->assertRedirect();

        $stored = Group::query()->whereKey($group->id)->firstOrFail();

        self::assertNotNull($stored->closed_at);
        self::assertNull($stored->deleted_at, 'الإقفال أرشفة لا حذف ناعم.');
        self::assertSame(0, Group::query()->forOrganization((string) $organization->id)->open()->count());
        self::assertSame(1, Group::query()->forOrganization((string) $organization->id)->closed()->count());
    }

    public function test_closing_is_refused_without_a_reason(): void
    {
        [$organization, $actor] = $this->context();
        $program = Program::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($actor)
            ->post('/manage/archive/program/'.$program->id.'/close', ['reason' => ''])
            ->assertSessionHasErrors('reason');

        self::assertNull(Program::query()->whereKey($program->id)->value('closed_at'));
    }

    public function test_closing_is_refused_while_an_active_course_sits_under_the_program(): void
    {
        [$organization, $actor] = $this->context();
        $program = Program::factory()->create(['organization_id' => $organization->id]);
        $level = Level::factory()->for($program, 'program')->create();
        Course::factory()->create([
            'organization_id' => $organization->id,
            'level_id' => $level->id,
            'is_active' => true,
        ]);

        $this->actingAs($actor)
            ->post('/manage/archive/program/'.$program->id.'/close', ['reason' => 'محاولة مبكرة'])
            ->assertSessionHasErrors();

        self::assertNull(Program::query()->whereKey($program->id)->value('closed_at'));
    }

    public function test_reopening_returns_the_item_to_the_working_view(): void
    {
        [$organization, $actor] = $this->context();
        $program = Program::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($actor)->post('/manage/archive/program/'.$program->id.'/close', ['reason' => 'انتهى']);
        $this->actingAs($actor)
            ->post('/manage/archive/program/'.$program->id.'/reopen', ['reason' => 'دفعة جديدة'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $stored = Program::query()->whereKey($program->id)->firstOrFail();

        self::assertNull($stored->closed_at);
        self::assertNull($stored->closure_summary);
    }

    public function test_preview_reports_the_summary_and_whether_it_can_be_closed(): void
    {
        [$organization, $actor] = $this->context();
        $program = Program::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($actor)
            ->getJson('/manage/archive/program/'.$program->id.'/preview')
            ->assertOk()
            ->assertJsonPath('kind', 'program')
            ->assertJsonPath('closable', true)
            ->assertJsonPath('summary.kind', 'program');
    }

    public function test_an_admin_without_the_permission_cannot_close_even_by_calling_the_route(): void
    {
        [$organization, $actor] = $this->context();
        $program = Program::factory()->create(['organization_id' => $organization->id]);

        $this->grant(['admin.panel.access', 'group.view']);

        $this->actingAs($actor)
            ->post('/manage/archive/program/'.$program->id.'/close', ['reason' => 'محاولة بلا صلاحية'])
            ->assertForbidden();

        self::assertNull(Program::query()->whereKey($program->id)->value('closed_at'));
    }

    public function test_another_organization_cannot_reach_the_record(): void
    {
        [, $actor] = $this->context();
        $stranger = Organization::factory()->create();
        $program = Program::factory()->create(['organization_id' => $stranger->id]);

        $this->actingAs($actor)
            ->post('/manage/archive/program/'.$program->id.'/close', ['reason' => 'محاولة عابرة'])
            ->assertNotFound();

        self::assertNull(Program::query()->whereKey($program->id)->value('closed_at'));
    }

    public function test_a_closed_program_and_group_disappear_from_the_courses_page_but_keep_their_rows(): void
    {
        [$organization, $actor] = $this->context();
        $program = Program::factory()->create(['organization_id' => $organization->id]);
        $level = Level::factory()->for($program, 'program')->create();
        $course = Course::factory()->create([
            'organization_id' => $organization->id, 'level_id' => $level->id, 'is_active' => false,
        ]);
        $group = Group::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($actor)->get('/manage/courses')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('programs.0.id', (string) $program->id)
                ->where('groups.0.id', (string) $group->id));

        $this->actingAs($actor)->post('/manage/archive/program/'.$program->id.'/close', ['reason' => 'انتهى الموسم'])
            ->assertSessionHasNoErrors();
        $this->actingAs($actor)->post('/manage/archive/group/'.$group->id.'/close', ['reason' => 'أنهت مدتها'])
            ->assertSessionHasNoErrors();

        $this->actingAs($actor)->get('/manage/courses')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('programs', [])
                ->where('courses', [])
                ->where('groups', []));

        // الاختفاء من الواجهة لا يعني فقد الصفوف: كلها ما زالت في القاعدة.
        self::assertTrue(Program::query()->whereKey($program->id)->exists());
        self::assertTrue(Course::query()->whereKey($course->id)->exists());
        self::assertTrue(Group::query()->whereKey($group->id)->exists());
    }

    public function test_a_closed_course_leaves_the_catalog_but_is_still_resolvable_by_id(): void
    {
        [$organization, $actor] = $this->context();
        $program = Program::factory()->create(['organization_id' => $organization->id]);
        $level = Level::factory()->for($program, 'program')->create();
        $course = Course::factory()->create([
            'organization_id' => $organization->id, 'level_id' => $level->id, 'is_active' => true,
        ]);

        $catalog = app(AcademicCatalogQueries::class);
        self::assertCount(1, $catalog->courses((string) $organization->id, (string) $program->id));

        $this->actingAs($actor)->post('/manage/archive/course/'.$course->id.'/close', ['reason' => 'انتهى'])
            ->assertSessionHasNoErrors();

        self::assertSame([], $catalog->courses((string) $organization->id, (string) $program->id));

        // سجلّ قديم يشير إلى هذا الكورس يجب أن يظل قادرًا على عرض اسمه.
        self::assertArrayHasKey(
            (string) $course->id,
            $catalog->coursesByIds((string) $organization->id, [(string) $course->id]),
        );
    }

    public function test_a_closed_level_leaves_the_courses_screen_and_takes_its_courses_with_it(): void
    {
        [$organization, $actor] = $this->context();
        $program = Program::factory()->create(['organization_id' => $organization->id]);
        $level = Level::factory()->for($program, 'program')->create();
        $course = Course::factory()->create([
            'organization_id' => $organization->id, 'level_id' => $level->id, 'is_active' => false,
        ]);

        $this->actingAs($actor)->get('/manage/courses')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('levels.0.id', (string) $level->id)
                ->where('courses.0.id', (string) $course->id));

        $this->actingAs($actor)
            ->post('/manage/archive/level/'.$level->id.'/close', ['reason' => 'المستوى التمهيدي انتهى'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->actingAs($actor)->get('/manage/courses')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('levels', [])
                // الكورس تحت مستوى مُقفل يختفي معه، ولا يبقى معلّقًا بلا مستوى.
                ->where('courses', []));

        self::assertTrue(Level::query()->whereKey($level->id)->exists());
        self::assertTrue(Course::query()->whereKey($course->id)->exists());
    }

    public function test_a_closed_level_is_listed_in_the_archive_and_can_be_reopened(): void
    {
        [$organization, $actor] = $this->context();
        $program = Program::factory()->create(['organization_id' => $organization->id]);
        $level = Level::factory()->for($program, 'program')->create();

        $this->actingAs($actor)
            ->post('/manage/archive/level/'.$level->id.'/close', ['reason' => 'انتهى'])
            ->assertSessionHasNoErrors();

        $this->actingAs($actor)->get('/manage/archive')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('sections.levels.0.id', (string) $level->id)
                ->where('sections.levels.0.summary.kind', 'level'));

        $this->actingAs($actor)
            ->post('/manage/archive/level/'.$level->id.'/reopen', ['reason' => 'دفعة جديدة'])
            ->assertSessionHasNoErrors();

        self::assertNull(Level::query()->whereKey($level->id)->value('closed_at'));
    }

    public function test_a_level_of_another_organization_cannot_be_reached(): void
    {
        [, $actor] = $this->context();
        $stranger = Organization::factory()->create();
        $program = Program::factory()->create(['organization_id' => $stranger->id]);
        $level = Level::factory()->for($program, 'program')->create();

        $this->actingAs($actor)
            ->post('/manage/archive/level/'.$level->id.'/close', ['reason' => 'محاولة عابرة'])
            ->assertNotFound();

        self::assertNull(Level::query()->whereKey($level->id)->value('closed_at'));
    }

    /** @param list<string> $permissions */
    private function grant(array $permissions): void
    {
        foreach ($this->permissions as $ability) {
            Gate::define($ability, fn (): bool => in_array($ability, $permissions, true));
        }
    }

    /** @return array{0: Organization, 1: User} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->inOrganization((string) $organization->id)->create();

        return [$organization, $actor];
    }
}
