<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Learning\LearningLibraryData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Modules\Content\Domain\Enums\MaterialStatus;
use Modules\Content\Domain\Enums\MaterialType;
use Modules\Content\Domain\Models\CourseMaterial;
use Modules\Identity\Domain\Models\User;
use Tests\TestCase;

final class LearningLibraryTest extends TestCase
{
    use CreatesSessionParticipant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 9, 6)->setTime(12, 0));
        config(['console.enabled' => true]);
        Gate::define('content.view', static fn (): bool => true);
    }

    public function test_student_reads_only_published_current_enrolled_course_metadata_and_foreign_urls_are_denied(): void
    {
        [$actor, $session, $participant] = $this->student();
        $visible = $this->material($session, ['visible_from' => now(), 'visible_to' => now()->addMinute()]);
        $hidden = [
            $this->material($session, ['status' => MaterialStatus::Draft]),
            $this->material($session, ['status' => MaterialStatus::Unpublished]),
            $this->material($session, ['visible_from' => now()->addMinute()]),
            $this->material($session, ['visible_to' => now()]),
        ];
        [, $foreign] = $this->student();
        $hidden[] = $this->material($foreign, ['title' => ['ar' => 'سري لمؤسسة أخرى']]);
        $otherCourse = $this->otherCourse($session);
        $hidden[] = $this->material([...$session, 'course_id' => $otherCourse]);
        $this->actingAs($actor)->get('/learn/student/library')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Learning/Library')
                ->has('materials.data', 1)->where('materials.data.0.id', $visible->id)
                ->missing('materials.data.0.disk')->missing('materials.data.0.path')
                ->missing('materials.data.0.external_url')->has('courses', 1));
        $this->get('/learn/student/library/'.$visible->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('selected.id', $visible->id)->where('materials', null)
                ->missing('selected.path')->missing('selected.disk')->missing('selected.external_url'));
        foreach ($hidden as $material) {
            $this->get('/learn/student/library/'.$material->id)->assertNotFound();
            $this->get('/learn/student/library/'.$material->id.'/open')->assertNotFound();
        }
        $this->assertDatabaseHas('enrollments', ['id' => $participant['enrollment_id'], 'status' => 'active']);
    }

    public function test_private_download_is_streamed_and_revoked_immediately_after_pause_without_changing_the_file(): void
    {
        [$actor, $session, $participant] = $this->student();
        Storage::fake('local');
        Storage::fake('public');
        $path = 'course-materials/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 library-private');
        $material = $this->material($session, ['path' => $path, 'title' => ['ar' => 'مراجعة التجويد']]);
        $response = $this->actingAs($actor)->get('/learn/student/library/'.$material->id.'/open');
        $response->assertOk()->assertDownload()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("filename*=utf-8''".rawurlencode('مراجعة التجويد.pdf'), (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('%PDF-1.4 library-private', $response->streamedContent());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        Storage::disk('public')->assertMissing($path);
        $this->get('/storage/'.$path)->assertForbidden();

        DB::table('enrollments')->where('id', $participant['enrollment_id'])->update(['status' => 'paused']);
        $this->get('/learn/student/library')->assertOk()->assertInertia(fn (Assert $page) => $page->has('materials.data', 0));
        $this->get('/learn/student/library/'.$material->id.'/open')->assertNotFound();
        Storage::disk('local')->assertExists($path);
        $this->assertDatabaseHas('course_materials', ['id' => $material->id, 'path' => $path, 'disk' => 'local']);
    }

    public function test_qualification_alone_does_not_grant_teacher_access_and_current_assignment_is_rechecked(): void
    {
        [, $session, $participant] = $this->student();
        $teacher = User::query()->findOrFail(DB::table('staff_profiles')->where('id', $session['staff_profile_id'])->value('user_id'));
        $material = $this->material($session);
        DB::table('teacher_courses')->insert(['id' => (string) Str::ulid(), 'staff_profile_id' => $session['staff_profile_id'], 'course_id' => $session['course_id'], 'qualified_by' => $teacher->id]);
        $this->actingAs($teacher)->get('/learn/teacher/library')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('materials.data', 0));
        $schedule = (string) Str::ulid();
        DB::table('schedules')->insert([
            'id' => $schedule, 'organization_id' => $session['organization_id'], 'course_id' => $session['course_id'],
            'staff_profile_id' => $session['staff_profile_id'], 'student_profile_id' => $participant['student_profile_id'],
            'session_type' => 'regular', 'rrule' => 'FREQ=WEEKLY;BYDAY=MO', 'start_time' => '16:00',
            'duration_minutes' => 30, 'timezone' => 'UTC', 'starts_on' => '2026-09-01',
            'is_active' => true, 'materialized_until' => '2026-09-06', 'created_by' => $teacher->id,
        ]);
        $this->get('/learn/teacher/library')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('materials.data', 1)->where('materials.data.0.id', $material->id));
        DB::table('schedules')->where('id', $schedule)->update(['ends_on' => '2026-09-05']);
        $this->get('/learn/teacher/library/'.$material->id)->assertNotFound();
        DB::table('schedules')->where('id', $schedule)->update(['ends_on' => null, 'starts_on' => '2026-09-07']);
        $this->get('/learn/teacher/library/'.$material->id)->assertNotFound();
        DB::table('schedules')->where('id', $schedule)->update(['starts_on' => '2026-09-01']);
        DB::table('courses')->where('id', $session['course_id'])->update(['is_active' => false]);
        $this->get('/learn/teacher/library/'.$material->id)->assertNotFound();
    }

    public function test_unsafe_file_references_and_links_are_rejected_but_approved_https_links_open(): void
    {
        [$actor, $session] = $this->student();
        Storage::fake('local');
        Storage::disk('local')->put('secrets.pdf', 'private unrelated document');
        $this->actingAs($actor);
        foreach (['course-materials/../secrets.pdf', 'secrets.pdf', '/course-materials/file.pdf', 'course-materials\\secret.pdf'] as $path) {
            $material = $this->material($session, ['path' => $path]);
            $this->get('/learn/student/library/'.$material->id.'/open')->assertNotFound();
        }
        $badDisk = $this->material($session, ['disk' => 'recordings']);
        $this->get('/learn/student/library/'.$badDisk->id.'/open')->assertNotFound();
        foreach (['javascript:alert(1)', 'https://user:secret@example.test/material', 'file:///etc/passwd'] as $url) {
            $material = $this->material($session, ['type' => MaterialType::Link, 'external_url' => $url, 'path' => null, 'disk' => null]);
            $this->get('/learn/student/library/'.$material->id.'/open')->assertNotFound();
        }
        $link = $this->material($session, ['type' => MaterialType::Link, 'external_url' => 'https://example.test/material?q=1', 'path' => null, 'disk' => null]);
        $this->get('/learn/student/library/'.$link->id.'/open')->assertRedirect('https://example.test/material?q=1')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_filters_preview_and_pagination_keep_only_scoped_data_and_handle_invalid_get_values(): void
    {
        [$actor, $session] = $this->student();
        config(['content.library.per_page' => 1, 'content.library.preview_items' => 1]);
        $this->material($session, ['title' => ['ar' => 'مراجعة 01']]);
        $this->material($session, ['title' => ['ar' => 'مراجعة 02']]);
        $this->actingAs($actor)->get('/learn/student/library?search=مراجعة&type=file&course='.$session['course_id'])
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('materials.total', 2)->has('materials.data', 1)
            ->where('materials.next_page_url', fn ($url) => str_contains($url, 'type=file') && str_contains($url, 'course='.$session['course_id']) && str_contains($url, 'search=')));
        $this->get('/learn/student/library?search[]=x&type[]=x&course[]=x&page[]=x')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('filters', ['search' => '', 'type' => '', 'course' => ''])->where('materials.current_page', 1));
        $request = Request::create('/learn/student');
        $request->setUserResolver(fn () => $actor);
        $preview = app(LearningLibraryData::class)->preview($request, 'student');
        $this->assertCount(1, $preview['items']);
        $this->assertSame(2, $preview['total']);
        $this->assertArrayNotHasKey('path', $preview['items'][0]);
        Gate::define('content.view', static fn (): bool => false);
        $this->assertSame([], app(LearningLibraryData::class)->preview($request, 'student')['items']);
        $this->assertNull(app(LearningLibraryData::class)->preview($request, 'student')['url']);
        $this->get('/learn/student/library')->assertForbidden();
    }

    public function test_authentication_permission_correct_portal_and_feature_gate_are_enforced(): void
    {
        [$actor, $session] = $this->student();
        $material = $this->material($session);
        $this->get('/learn/student/library')->assertRedirect();
        $this->actingAs($actor)->get('/learn/teacher/library')->assertForbidden();
        Gate::define('content.view', static fn (): bool => false);
        $this->get('/learn/student/library/'.$material->id.'/open')->assertForbidden();
        Gate::define('content.view', static fn (): bool => true);
        config(['console.enabled' => false]);
        $this->get('/learn/student/library')->assertNotFound();
    }

    /** @return array{User,array<string,mixed>,array<string,mixed>} */
    private function student(): array
    {
        $participant = (array) DB::table('session_participants')->find($this->createSessionParticipant());
        $session = (array) DB::table('sessions')->find($participant['session_id']);
        $actor = User::query()->findOrFail(DB::table('student_profiles')->where('id', $participant['student_profile_id'])->value('user_id'));

        return [$actor, $session, $participant];
    }

    /** @param array<string,mixed> $session @param array<string,mixed> $overrides */
    private function material(array $session, array $overrides = []): CourseMaterial
    {
        return CourseMaterial::query()->create([
            'organization_id' => $session['organization_id'], 'course_id' => $session['course_id'],
            'title' => ['ar' => 'درس التجويد'], 'description' => ['ar' => 'ملخص الحصة للمراجعة'],
            'type' => MaterialType::File, 'status' => MaterialStatus::Published,
            'display_order' => 0, 'revision' => 1, 'disk' => 'local',
            'path' => 'course-materials/'.Str::ulid().'.pdf', 'size_bytes' => 23,
            'published_at' => now(), ...$overrides,
        ]);
    }

    /** @param array<string,mixed> $session */
    private function otherCourse(array $session): string
    {
        $course = (array) DB::table('courses')->find($session['course_id']);
        $level = (array) DB::table('levels')->find($course['level_id']);
        $program = (array) DB::table('programs')->find($level['program_id']);
        $newProgram = (string) Str::ulid();
        DB::table('programs')->insert([...$program, 'id' => $newProgram, 'code' => 'PRG-'.Str::random(8)]);
        $newLevel = (string) Str::ulid();
        DB::table('levels')->insert([...$level, 'id' => $newLevel, 'program_id' => $newProgram]);
        $newCourse = (string) Str::ulid();
        DB::table('courses')->insert([...$course, 'id' => $newCourse, 'level_id' => $newLevel, 'code' => 'CRS-'.Str::random(8)]);

        return $newCourse;
    }
}
