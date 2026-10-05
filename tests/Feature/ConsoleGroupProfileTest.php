<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Academics\Domain\Models\Course;
use Modules\Groups\Domain\Models\Group;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class ConsoleGroupProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_file_is_organization_scoped_and_preserves_read_only_access(): void
    {
        config(['console.enabled' => true]);
        $this->withoutVite();
        $actor = User::query()->findOrFail(Fixtures::userId());
        $permissions = ['admin.panel.access', 'group.view', 'group.manage'];
        foreach ($permissions as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->id === $actor->id);
        }
        $this->actingAs($actor);
        $course = Course::query()->findOrFail(Fixtures::courseId());
        $this->post('/manage/groups', [
            'code' => 'GROUP-FILE', 'name' => ['ar' => 'ملف مجموعة اختبار'],
            'timezone' => 'Africa/Cairo', 'program_ids' => [$course->level->program_id],
        ])->assertSessionHasNoErrors();
        $group = Group::query()->where('code', 'GROUP-FILE')->firstOrFail();
        Gate::define('group.manage', fn (): bool => false);
        Gate::define('student.view.any', fn (): bool => false);
        Gate::define('report.view', fn (): bool => false);
        $this->get('/manage/groups/'.$group->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Console/GroupProfile')->where('group.id', (string) $group->id)
            ->where('abilities.edit', false)->where('abilities.students', false)->where('members', [])
            ->where('summary', null)->has('programs', 1));
        $foreign = Organization::factory()->create();
        $group->update(['organization_id' => $foreign->id]);
        $this->get('/manage/groups/'.$group->id)->assertNotFound();
        $this->get('/manage/groups/'.Str::ulid())->assertNotFound();
        Gate::define('group.view', fn (): bool => false);
        $this->get('/manage/groups/'.$group->id)->assertForbidden();
    }

    public function test_directory_uses_actual_authorized_destinations_and_keeps_search_text(): void
    {
        config(['console.enabled' => true]);
        $this->withoutVite();
        $actor = User::query()->findOrFail(Fixtures::userId());
        Gate::define('admin.panel.access', fn (): bool => true);
        Gate::define('payroll.view', fn (): bool => false);
        $this->actingAs($actor)->get('/manage/directory?search='.urlencode('تسكين'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Console/Directory')->where('search', 'تسكين')
                ->where('sections', fn ($sections): bool => !collect($sections)->flatMap(fn ($section) => $section['items'])->contains(fn ($item): bool => $item['href'] === '/manage/teacher-dues')));
    }
}
