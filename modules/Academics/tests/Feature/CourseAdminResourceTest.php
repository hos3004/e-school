<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Presentation\Filament\Resources\CourseFilamentResource;
use Modules\Identity\Domain\Models\User;
use Shared\Support\LocalizedJsonColumn;
use Tests\TestCase;

/**
 * مورد الكورسات في لوحة الإدارة.
 *
 * يحرس عيبين كانا قائمين:
 *   1. الجدول كان يعرض كورسات كل المؤسسات — لا `getEloquentQuery` ولا أي عزل.
 *   2. `name` عمود jsonb معلَّم `searchable()`، والبحث الافتراضي يبني `LIKE`
 *      عليه فينهار الطلب بـ`operator does not exist: jsonb ~~ unknown`.
 */
final class CourseAdminResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_table_shows_only_courses_of_the_users_organization(): void
    {
        $mine = $this->academicContext('MINE');
        $other = $this->academicContext('OTHER');

        $this->actingAs($mine['user']);

        $ids = CourseFilamentResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($mine['course_id'], $ids);
        $this->assertNotContains(
            $other['course_id'],
            $ids,
            'مورد الكورسات يسرّب بيانات مؤسسة أخرى.',
        );
    }

    public function test_a_request_without_a_resolvable_organization_sees_nothing(): void
    {
        $this->academicContext('ORPHAN');

        /*
         * لا مستخدم في الجلسة — الحالة التي كان الكود القديم يتعامل معها
         * بتجاهل الشرط، فيعرض كل شيء. الآن يجب أن يعرض صفرًا.
         */
        $this->assertSame(0, CourseFilamentResource::getEloquentQuery()->count());
    }

    public function test_searching_a_localized_json_name_does_not_crash_and_matches_both_languages(): void
    {
        $context = $this->academicContext('SEARCH');
        $this->actingAs($context['user']);

        $search = LocalizedJsonColumn::search('courses.name');

        $byArabic = $search(CourseFilamentResource::getEloquentQuery(), 'تجويد')->pluck('id')->all();
        $byEnglish = $search(CourseFilamentResource::getEloquentQuery(), 'Tajweed')->pluck('id')->all();
        $noMatch = $search(CourseFilamentResource::getEloquentQuery(), 'zzz-not-here')->pluck('id')->all();

        $this->assertContains($context['course_id'], $byArabic);
        $this->assertContains(
            $context['course_id'],
            $byEnglish,
            'البحث يجب أن يجد الاسم بأي لغة مسجّلة لا بلغة الواجهة وحدها.',
        );
        $this->assertSame([], $noMatch);
    }

    public function test_sorting_a_localized_json_name_does_not_crash(): void
    {
        $context = $this->academicContext('SORT');
        $this->actingAs($context['user']);

        $sort = LocalizedJsonColumn::sort('courses.name');

        $asc = $sort(CourseFilamentResource::getEloquentQuery(), 'asc')->pluck('id')->all();
        $desc = $sort(CourseFilamentResource::getEloquentQuery(), 'desc')->pluck('id')->all();

        $this->assertContains($context['course_id'], $asc);
        $this->assertContains($context['course_id'], $desc);
    }

    public function test_a_direction_that_is_not_asc_or_desc_cannot_reach_the_query(): void
    {
        $context = $this->academicContext('INJECT');
        $this->actingAs($context['user']);

        $sort = LocalizedJsonColumn::sort('courses.name');

        // قيمة عدائية بدل asc/desc — يجب أن تُقيَّد لا أن تُدمج في SQL.
        $ids = $sort(CourseFilamentResource::getEloquentQuery(), 'asc; drop table courses')
            ->pluck('id')
            ->all();

        $this->assertContains($context['course_id'], $ids);
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('courses'));
    }

    public function test_the_courses_list_page_renders_for_a_permitted_user(): void
    {
        // الدخول إلى اللوحة نفسها صلاحية مستقلة عن إدارة الكورسات.
        Gate::define('course.manage', static fn (): bool => true);
        Gate::define('admin.panel.access', static fn (): bool => true);
        Gate::define('view', static fn (): bool => true);

        $context = $this->academicContext('RENDER');

        $this->actingAs($context['user'])
            ->get(CourseFilamentResource::getUrl('index', panel: 'admin'))
            ->assertOk();
    }

    /**
     * مؤسسة · مستخدم · برنامج · مستوى · كورس باسم عربي وإنجليزي.
     *
     * @return array<string, mixed>
     */
    private function academicContext(string $tag): array
    {
        $organizationId = (string) Str::ulid();
        DB::table('organizations')->insert([
            'id' => $organizationId,
            'name' => json_encode(['ar' => 'مدرسة', 'en' => 'School'], JSON_THROW_ON_ERROR),
            'slug' => strtolower($tag).'-'.strtolower((string) Str::ulid()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->inOrganization($organizationId)->create([
            'email' => Str::lower($tag.Str::random(8)).'@example.test',
        ]);

        $programId = (string) Str::ulid();
        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organizationId,
            'code' => $tag.'-PROG-'.Str::upper(Str::random(5)),
            'name' => json_encode(['ar' => 'برنامج', 'en' => 'Program'], JSON_THROW_ON_ERROR),
            'default_session_minutes' => 60,
            'currency' => 'EGP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $levelId = (string) Str::ulid();
        DB::table('levels')->insert([
            'id' => $levelId,
            'program_id' => $programId,
            'code' => 'L1',
            'name' => json_encode(['ar' => 'المستوى الأول', 'en' => 'Level one'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);

        $course = Course::query()->create([
            'organization_id' => $organizationId,
            'level_id' => $levelId,
            'code' => $tag.'-COURSE-'.Str::upper(Str::random(5)),
            'name' => ['ar' => 'أحكام التجويد', 'en' => 'Tajweed rules'],
            'is_active' => true,
        ]);

        return [
            'organization_id' => $organizationId,
            'user' => $user,
            'program_id' => $programId,
            'level_id' => $levelId,
            'course_id' => (string) $course->getKey(),
        ];
    }
}
