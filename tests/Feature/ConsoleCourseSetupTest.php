<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Groups\Domain\Models\Group;
use Modules\Identity\Domain\Models\User;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class ConsoleCourseSetupTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        config(['console.enabled' => true]);
        $this->actor = User::query()->findOrFail(Fixtures::userId());
        foreach (['admin.panel.access', 'course.manage', 'program.manage', 'group.view', 'group.manage'] as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->id === $this->actor->id);
        }
        $this->actingAs($this->actor);
        $this->withoutVite();
    }

    public function test_full_browser_payloads_are_filtered_per_kind_with_strict_models(): void
    {
        $teacherId = Fixtures::staffProfileId();
        $previous = Model::preventsSilentlyDiscardingAttributes();
        Model::preventSilentlyDiscardingAttributes();
        $this->withoutExceptionHandling();
        try {
            $this->post('/manage/courses/programs', [
                ...$this->programData(), 'description' => ['ar' => null],
                'duration_weeks' => null, 'start_date' => null, 'end_date' => null,
                'default_rate_amount' => null, 'language' => null, 'age_from' => null, 'age_to' => null,
                'level_id' => null, 'capacity' => null, 'unused_shared_field' => 'ignored',
            ])->assertRedirect()->assertSessionHasNoErrors();
            $program = Program::query()->where('code', 'CONSOLE-PROGRAM')->firstOrFail();
            $levelPayload = [
                'program_id' => $program->id, 'code' => 'BROWSER-LEVEL',
                'name' => ['ar' => 'مستوى من سياق الكورس'], 'description' => ['ar' => null], 'sort_order' => 0,
                'default_rate_amount' => null, 'is_active' => true, 'session_mode' => 'group',
                'capacity' => null, 'unused_shared_field' => 'ignored',
            ];
            $this->post('/manage/courses/levels', $levelPayload)->assertRedirect()->assertSessionHasNoErrors();
            $level = Level::query()->where('code', 'BROWSER-LEVEL')->firstOrFail();
            $this->patch('/manage/courses/levels/'.$level->id, [...$levelPayload, 'name' => ['ar' => 'مستوى معدل']])
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame('مستوى معدل', $level->fresh()->name['ar']);
            $this->assertSame($program->id, $level->program_id);
            $this->assertArrayNotHasKey('description', $level->getAttributes());

            $coursePayload = [
                'level_id' => $level->id, 'code' => 'BROWSER-COURSE', 'name' => ['ar' => 'كورس من المسودة'],
                'description' => ['ar' => null], 'sort_order' => 0, 'is_active' => true, 'session_mode' => 'group',
                'total_sessions' => null, 'default_duration_minutes' => null, 'sessions_per_week' => null,
                'target_gender' => null, 'age_from' => null, 'age_to' => null, 'unused_shared_field' => 'ignored',
            ];
            $this->post('/manage/courses/items', $coursePayload)->assertRedirect()->assertSessionHasNoErrors();
            $course = Course::query()->where('code', 'BROWSER-COURSE')->firstOrFail();
            $this->patch('/manage/courses/items/'.$course->id, $coursePayload)->assertRedirect()->assertSessionHasNoErrors();
            $this->assertArrayNotHasKey('sort_order', $course->getAttributes());

            $groupPayload = [
                'code' => 'BROWSER-GROUP', 'name' => ['ar' => 'مجموعة من المتصفح'], 'description' => ['ar' => null],
                'sort_order' => 0, 'capacity' => null, 'timezone' => 'Africa/Cairo', 'starts_on' => null, 'ends_on' => null,
                'program_ids' => [$program->id], 'default_rate_amount' => null, 'level_id' => $level->id,
                'unused_shared_field' => 'ignored',
            ];
            $this->post('/manage/groups', $groupPayload)->assertRedirect()->assertSessionHasNoErrors();
            $group = Group::query()->where('code', 'BROWSER-GROUP')->firstOrFail();
            $this->patch('/manage/groups/'.$group->id, $groupPayload)->assertRedirect()->assertSessionHasNoErrors();
            $this->assertArrayNotHasKey('description', $group->getAttributes());
            $this->assertSame('planning', $group->status->value);

            DB::table('teacher_courses')->insert([
                'id' => (string) Str::ulid(), 'staff_profile_id' => $teacherId, 'course_id' => $course->id,
                'qualified_by' => $this->actor->id, 'qualified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->post('/manage/groups/'.$group->id.'/teachers', [
                'course_id' => $course->id, 'staff_profile_id' => $teacherId, 'role' => 'lead',
                'assigned_from' => '2026-09-07', 'assigned_to' => null,
                'description' => ['ar' => null], 'name' => ['ar' => null], 'sort_order' => 0,
                'capacity' => null, 'program_ids' => [$program->id], 'group_id' => (string) Str::ulid(),
                'unused_shared_field' => 'ignored',
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('group_teachers', [
                'group_id' => $group->id, 'staff_profile_id' => $teacherId, 'course_id' => $course->id, 'role' => 'lead',
            ]);
        } finally {
            Model::preventSilentlyDiscardingAttributes($previous);
        }
    }

    public function test_creates_a_complete_academic_path_without_a_manual_reason_and_records_audit(): void
    {
        $this->post('/manage/courses/programs', $this->programData())->assertSessionHasNoErrors()->assertRedirect();
        $program = Program::query()->where('code', 'CONSOLE-PROGRAM')->firstOrFail();
        $this->post('/manage/courses/levels', [
            'program_id' => $program->id, 'code' => 'LEVEL-1', 'name' => ['ar' => 'المستوى الأول'], 'sort_order' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $level = Level::query()->where('program_id', $program->id)->firstOrFail();
        $this->post('/manage/courses/items', [
            'level_id' => $level->id, 'code' => 'COURSE-1', 'name' => ['ar' => 'دورة المحادثة'],
            'session_mode' => 'group', 'is_active' => true, 'total_sessions' => 12,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $course = Course::query()->where('code', 'COURSE-1')->firstOrFail();
        $this->assertSame($this->actor->organization_id, $course->organization_id);
        $this->assertSame($level->id, $course->level_id);
        $this->assertDatabaseHas('audit_log', ['action' => 'academics.course_created', 'actor_id' => $this->actor->id, 'auditable_id' => $course->id]);
        $this->get('/manage/courses')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Console/Courses')->has('courses', 1)->has('programs', 1)->has('levels', 1)
            ->where('courses.0.label', 'دورة المحادثة'));
    }

    public function test_updates_existing_program_level_and_course_through_their_actions(): void
    {
        $course = Course::query()->findOrFail(Fixtures::courseId());
        $level = Level::query()->findOrFail($course->level_id);
        $program = Program::query()->findOrFail($level->program_id);
        $this->patch('/manage/courses/programs/'.$program->id, [
            ...$this->programData(), 'code' => $program->code, 'name' => ['ar' => 'اسم البرنامج المحدث'],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->patch('/manage/courses/levels/'.$level->id, [
            'program_id' => $program->id, 'code' => $level->code, 'name' => ['ar' => 'المستوى المحدث'], 'sort_order' => 2,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->patch('/manage/courses/items/'.$course->id, [
            'level_id' => $level->id, 'code' => $course->code, 'name' => ['ar' => 'الكورس المحدث'], 'session_mode' => 'group', 'is_active' => true,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('الكورس المحدث', $course->fresh()->name['ar']);
        $this->assertSame('المستوى المحدث', $level->fresh()->name['ar']);
        $this->assertSame('اسم البرنامج المحدث', $program->fresh()->name['ar']);
    }

    public function test_group_is_created_as_draft_and_cannot_activate_until_requirements_are_met(): void
    {
        $program = Program::query()->findOrFail(Level::query()->findOrFail(Course::query()->findOrFail(Fixtures::courseId())->level_id)->program_id);
        $this->post('/manage/groups', [
            'code' => 'CONSOLE-GROUP', 'name' => ['ar' => 'مجموعة التجهيز'],
            'timezone' => 'Africa/Cairo', 'program_ids' => [$program->id],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $group = Group::query()->where('code', 'CONSOLE-GROUP')->firstOrFail();
        $this->assertSame('planning', $group->status->value);
        $this->assertDatabaseHas('group_programs', ['group_id' => $group->id, 'program_id' => $program->id]);
        $this->post('/manage/groups/'.$group->id.'/activate')->assertSessionHasErrors('form');
        $this->assertSame('planning', $group->fresh()->status->value);
        $this->get('/manage/groups')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Console/Courses')
            ->where('initialTab', 'groups')->where('groups.0.can_activate', false)
            ->where('groups.0.missing', ['capacity', 'starts_on', 'teacher']));
    }

    public function test_group_and_program_link_are_rolled_back_if_the_program_is_from_another_organization(): void
    {
        $otherId = $this->otherOrganization();
        $other = Program::factory()->create(['organization_id' => $otherId]);
        $this->post('/manage/groups', [
            'code' => 'FOREIGN-GROUP', 'name' => ['ar' => 'لا تحفظ'],
            'timezone' => 'UTC', 'program_ids' => [$other->id],
        ])->assertSessionHasErrors('form');
        $this->assertDatabaseMissing('groups', ['code' => 'FOREIGN-GROUP']);
    }

    public function test_organization_is_server_owned_and_foreign_academic_records_cannot_be_changed(): void
    {
        $otherId = $this->otherOrganization();
        $otherProgram = Program::factory()->create(['organization_id' => $otherId]);
        $otherLevel = Level::factory()->create(['program_id' => $otherProgram->id]);
        $this->post('/manage/courses/programs', [...$this->programData(), 'organization_id' => $otherId])
            ->assertSessionHasErrors('organization_id');
        $this->patch('/manage/courses/programs/'.$otherProgram->id, $this->programData())->assertNotFound();
        $this->post('/manage/courses/items', [
            'level_id' => $otherLevel->id, 'code' => 'FOREIGN-COURSE', 'name' => ['ar' => 'غير مسموح'],
            'session_mode' => 'group', 'is_active' => true,
        ])->assertSessionHasErrors('form');
        $this->assertDatabaseMissing('courses', ['code' => 'FOREIGN-COURSE']);
        $this->get('/manage/courses')->assertInertia(fn (Assert $page) => $page->has('programs', 0));
    }

    public function test_specific_permissions_are_required_for_reading_and_writing(): void
    {
        $reader = User::query()->findOrFail(Fixtures::userId());
        Gate::define('admin.panel.access', static fn (): bool => true);
        $this->actingAs($reader);
        $this->get('/manage/courses')->assertForbidden();
        $this->get('/manage/courses/programs')->assertForbidden();
        $this->get('/manage/groups')->assertForbidden();
        $this->post('/manage/courses/programs', $this->programData())->assertForbidden();
        $this->post('/manage/groups', [])->assertForbidden();
    }

    public function test_qualified_teacher_assignment_enables_activation_and_is_audited(): void
    {
        $course = Course::query()->findOrFail(Fixtures::courseId());
        $programId = Level::query()->findOrFail($course->level_id)->program_id;
        $teacherId = Fixtures::staffProfileId();
        DB::table('teacher_courses')->insert([
            'id' => (string) Str::ulid(), 'staff_profile_id' => $teacherId, 'course_id' => $course->id,
            'qualified_by' => $this->actor->id, 'qualified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->post('/manage/groups', [
            'code' => 'READY-GROUP', 'name' => ['ar' => 'مجموعة جاهزة'],
            'timezone' => 'Africa/Cairo', 'program_ids' => [$programId], 'capacity' => 5, 'starts_on' => '2026-09-07',
        ])->assertSessionHasNoErrors();
        $group = Group::query()->where('code', 'READY-GROUP')->firstOrFail();
        $this->post('/manage/groups/'.$group->id.'/teachers', [
            'course_id' => $course->id, 'staff_profile_id' => $teacherId, 'role' => 'lead', 'assigned_from' => '2026-09-07',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/manage/groups/'.$group->id.'/activate')->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('active', $group->fresh()->status->value);
        $this->assertDatabaseHas('audit_log', ['action' => 'groups.teacher_assigned', 'actor_id' => $this->actor->id]);
        $this->assertDatabaseHas('audit_log', ['action' => 'groups.group_activated', 'auditable_id' => $group->id]);
        $this->patch('/manage/groups/'.$group->id, [
            'code' => $group->code, 'name' => ['ar' => 'المجموعة نفسها'], 'timezone' => 'Africa/Cairo',
            'program_ids' => [$programId], 'capacity' => null, 'starts_on' => null,
        ])->assertSessionHasErrors('form');
        $this->assertSame(5, $group->fresh()->capacity);
    }

    public function test_unqualified_teacher_or_course_from_an_unlinked_program_cannot_be_assigned(): void
    {
        $course = Course::query()->findOrFail(Fixtures::courseId());
        $programId = Level::query()->findOrFail($course->level_id)->program_id;
        $this->post('/manage/groups', [
            'code' => 'QUALIFY-GROUP', 'name' => ['ar' => 'تحقق التأهيل'], 'timezone' => 'UTC', 'program_ids' => [$programId],
        ])->assertSessionHasNoErrors();
        $group = Group::query()->where('code', 'QUALIFY-GROUP')->firstOrFail();
        $teacherId = Fixtures::staffProfileId();
        $this->post('/manage/groups/'.$group->id.'/teachers', [
            'course_id' => $course->id, 'staff_profile_id' => $teacherId, 'role' => 'lead', 'assigned_from' => '2026-09-07',
        ])->assertSessionHasErrors('form');
        $differentProgram = Program::factory()->create(['organization_id' => $this->actor->organization_id]);
        $differentLevel = Level::factory()->create(['program_id' => $differentProgram->id]);
        $differentCourse = Course::factory()->create(['organization_id' => $this->actor->organization_id, 'level_id' => $differentLevel->id]);
        DB::table('teacher_courses')->insert([
            'id' => (string) Str::ulid(), 'staff_profile_id' => $teacherId, 'course_id' => $differentCourse->id,
            'qualified_by' => $this->actor->id, 'qualified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->post('/manage/groups/'.$group->id.'/teachers', [
            'course_id' => $differentCourse->id, 'staff_profile_id' => $teacherId, 'role' => 'lead', 'assigned_from' => '2026-09-07',
        ])->assertSessionHasErrors('form');
        $this->assertDatabaseMissing('group_teachers', ['group_id' => $group->id]);
    }

    public function test_group_reader_cannot_receive_program_pricing(): void
    {
        $this->post('/manage/courses/programs', [...$this->programData(), 'default_rate_amount' => '75.00'])->assertSessionHasNoErrors();
        Gate::define('program.manage', static fn (): bool => false);
        $this->get('/manage/groups')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('programs', 1)->missing('programs.0.default_rate')->missing('programs.0.default_rate_amount')->where('abilities.programs', false));
    }

    public function test_program_rate_accepts_major_units_and_returns_major_units_for_editing(): void
    {
        $this->post('/manage/courses/programs', [...$this->programData(), 'default_rate_amount' => '100.50'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $program = Program::query()->where('code', 'CONSOLE-PROGRAM')->firstOrFail();
        $this->assertSame(10050, $program->default_rate);
        $this->get('/manage/courses/programs')->assertInertia(fn (Assert $page) => $page
            ->where('programs.0.default_rate_amount', '100.50')->missing('programs.0.default_rate'));
        $this->patch('/manage/courses/programs/'.$program->id, [...$this->programData(), 'default_rate_amount' => '100.51'])
            ->assertSessionHasNoErrors();
        $this->assertSame(10051, $program->fresh()->default_rate);
    }

    public function test_program_rate_respects_zero_and_three_decimal_currencies_without_rounding(): void
    {
        foreach ([['JPY', '100', 100], ['KWD', '100.501', 100501]] as [$currency, $amount, $minor]) {
            $this->post('/manage/courses/programs', [...$this->programData(), 'code' => 'RATE-'.$currency, 'currency' => $currency, 'default_rate_amount' => $amount])
                ->assertSessionHasNoErrors();
            $program = Program::query()->where('code', 'RATE-'.$currency)->firstOrFail();
            $this->assertSame($minor, $program->default_rate);
            $this->get('/manage/courses/programs')->assertInertia(fn (Assert $page) => $page->where('programs', fn ($programs): bool => collect($programs)->contains(fn ($item): bool => $item['code'] === 'RATE-'.$currency && $item['default_rate_amount'] === $amount)));
        }
        foreach ([['JPY', '100.50'], ['EGP', '100.501'], ['KWD', '100.5019'], ['EGP', '99999999999999999999999']] as [$currency, $amount]) {
            $this->post('/manage/courses/programs', [...$this->programData(), 'currency' => $currency, 'default_rate_amount' => $amount])
                ->assertSessionHasErrors('default_rate_amount');
        }
        $this->post('/manage/courses/programs', [...$this->programData(), 'currency' => 'ZZZ', 'default_rate_amount' => '100'])
            ->assertSessionHasErrors('currency');
    }

    public function test_arabic_only_edits_preserve_existing_name_and_description_translations(): void
    {
        $course = Course::query()->findOrFail(Fixtures::courseId());
        $level = Level::query()->findOrFail($course->level_id);
        $program = Program::query()->findOrFail($level->program_id);
        foreach ([$program, $level, $course] as $record) {
            $record->update(['name' => ['ar' => 'قبل', 'en' => 'Existing name', 'fr' => 'Nom existant']]);
        }
        foreach ([$program, $course] as $record) {
            $record->update(['description' => ['ar' => 'وصف قديم', 'en' => 'Existing description', 'fr' => 'Description existante']]);
        }
        $this->patch('/manage/courses/programs/'.$program->id, [...$this->programData(), 'code' => $program->code, 'name' => ['ar' => 'اسم عربي جديد'], 'description' => ['ar' => 'وصف عربي جديد']])->assertSessionHasNoErrors();
        $this->patch('/manage/courses/levels/'.$level->id, ['program_id' => $program->id, 'code' => $level->code, 'name' => ['ar' => 'مستوى عربي']])->assertSessionHasNoErrors();
        $this->patch('/manage/courses/items/'.$course->id, ['level_id' => $level->id, 'code' => $course->code, 'name' => ['ar' => 'كورس عربي'], 'description' => ['ar' => 'وصف عربي جديد'], 'session_mode' => 'group', 'is_active' => true])->assertSessionHasNoErrors();
        foreach ([$program, $level, $course] as $record) {
            $this->assertSame('Existing name', $record->fresh()->name['en']);
            $this->assertSame('Nom existant', $record->fresh()->name['fr']);
        }
        foreach ([$program, $course] as $record) {
            $this->assertSame('Existing description', $record->fresh()->description['en']);
            $this->assertSame('Description existante', $record->fresh()->description['fr']);
        }
        $this->post('/manage/groups', ['code' => 'LANG-GROUP', 'name' => ['ar' => 'مجموعة', 'en' => 'Existing group', 'fr' => 'Groupe existant'], 'timezone' => 'UTC', 'program_ids' => [$program->id]])->assertSessionHasNoErrors();
        $group = Group::query()->where('code', 'LANG-GROUP')->firstOrFail();
        $this->patch('/manage/groups/'.$group->id, ['code' => 'LANG-GROUP', 'name' => ['ar' => 'مجموعة عربية'], 'timezone' => 'UTC', 'program_ids' => [$program->id]])->assertSessionHasNoErrors();
        $this->assertSame('Existing group', $group->fresh()->name['en']);
        $this->assertSame('Groupe existant', $group->fresh()->name['fr']);
    }

    public function test_course_can_save_its_first_group_in_the_same_context(): void
    {
        $original = Course::query()->findOrFail(Fixtures::courseId());
        $level = Level::query()->findOrFail($original->level_id);
        $response = $this->post('/manage/courses/items', [
            'code' => 'FIRST-GROUP-COURSE', 'name' => ['ar' => 'كورس ومجموعة من نموذج واحد'],
            'level_id' => $level->id, 'session_mode' => 'group', 'is_active' => true,
            'first_group_name' => 'المجموعة الأولى للكورس', 'first_group_capacity' => 12,
            'first_group_starts_on' => '2026-09-20', 'first_group_ends_on' => '2026-12-20',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $course = Course::query()->where('code', 'FIRST-GROUP-COURSE')->firstOrFail();
        $group = Group::query()->where('name->ar', 'المجموعة الأولى للكورس')->firstOrFail();
        self::assertSame((string) $this->actor->organization_id, (string) $group->organization_id);
        self::assertSame('planning', $group->status->value);
        self::assertSame(12, $group->capacity);
        self::assertSame('2026-09-20', $group->starts_on->toDateString());
        self::assertTrue($group->programs()->where('program_id', $level->program_id)->exists());
        $response->assertRedirect(route('console.groups.index', ['group' => $group->id, 'course' => $course->id, 'action' => 'assign']));
        self::assertTrue(DB::table('audit_log')->where('action', 'groups.group_created')->where('auditable_id', $group->id)->exists());
    }

    public function test_first_group_is_optional_but_needs_group_permission_and_a_group_course(): void
    {
        $course = Course::query()->findOrFail(Fixtures::courseId());
        $payload = ['code' => 'OPTIONAL-GROUP', 'name' => ['ar' => 'دراسة اختبار'], 'level_id' => $course->level_id, 'session_mode' => 'group', 'is_active' => true];
        Gate::define('group.manage', fn (): bool => false);
        $this->post('/manage/courses/items', [...$payload, 'first_group_name' => 'مجموعة غير مسموحة'])->assertForbidden();
        self::assertFalse(Course::query()->where('code', 'OPTIONAL-GROUP')->exists());
        $this->post('/manage/courses/items', $payload)->assertSessionHasNoErrors();
        Gate::define('group.manage', fn (): bool => true);
        $this->post('/manage/courses/items', [...$payload, 'code' => 'INDIVIDUAL-GROUP', 'session_mode' => 'individual', 'first_group_name' => 'مجموعة'])->assertSessionHasErrors('first_group_name');
        self::assertFalse(Course::query()->where('code', 'INDIVIDUAL-GROUP')->exists());
    }

    /** @return array<string, mixed> */
    private function programData(): array
    {
        return [
            'code' => 'CONSOLE-PROGRAM', 'name' => ['ar' => 'برنامج اللغة'],
            'program_type' => 'ongoing', 'default_session_minutes' => 45, 'currency' => 'EGP',
            'target_gender' => 'all', 'is_active' => true,
        ];
    }

    private function otherOrganization(): string
    {
        $id = (string) Str::ulid();
        DB::table('organizations')->insert(['id' => $id, 'name' => json_encode(['ar' => 'مؤسسة أخرى']), 'slug' => 'other-'.$id, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }
}
