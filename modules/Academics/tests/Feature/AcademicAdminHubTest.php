<?php

declare(strict_types=1);

namespace Modules\Academics\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Academics\Application\Queries\AcademicAdministrationQueryService;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Academics\Domain\Models\ProgramCategory;
use Modules\Academics\Domain\Models\ProgramEligibility;
use Modules\Academics\Presentation\Filament\Resources\CourseFilamentResource;
use Modules\Academics\Presentation\Filament\Resources\LevelFilamentResource;
use Modules\Academics\Presentation\Filament\Resources\ProgramFilamentResource;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Tests\TestCase;

final class AcademicAdminHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_level_and_course_hubs_render_real_academic_context(): void
    {
        Gate::before(static fn (): bool => true);
        Filament::setCurrentPanel('admin');

        $organization = Organization::factory()->create();
        $operator = User::factory()->inOrganization((string) $organization->id)->create();
        $program = Program::factory()->create([
            'organization_id' => (string) $organization->id,
            'code' => 'PRG-HUB-01',
            'name' => ['ar' => 'برنامج التلاوة', 'en' => 'Recitation Program'],
        ]);
        ProgramEligibility::query()->create([
            'program_id' => (string) $program->id,
            'countries' => [],
            'regions' => [],
            'manual_approval_required' => true,
        ]);
        $level = Level::factory()->for($program, 'program')->create([
            'code' => 'LVL-HUB-01',
            'name' => ['ar' => 'المستوى التمهيدي', 'en' => 'Foundation Level'],
        ]);
        $course = Course::factory()->create([
            'organization_id' => (string) $organization->id,
            'level_id' => (string) $level->id,
            'code' => 'CRS-HUB-01',
            'name' => ['ar' => 'أساسيات التلاوة', 'en' => 'Recitation Basics'],
        ]);
        $category = ProgramCategory::query()->create([
            'organization_id' => (string) $organization->id,
            'program_id' => (string) $program->id,
            'code' => 'CAT-HUB-01',
            'name' => ['ar' => 'الأساسيات', 'en' => 'Foundations'],
        ]);
        $course->categories()->attach((string) $category->id);

        $this->actingAs($operator)
            ->get(ProgramFilamentResource::getUrl('create', panel: 'admin'))
            ->assertOk()
            ->assertSeeText(__('academics::filament.program.sections.eligibility'))
            ->assertDontSee('data.organization_id', false);

        $this->get(ProgramFilamentResource::getUrl('view', ['record' => $program], panel: 'admin'))
            ->assertOk()->assertSeeText('PRG-HUB-01')->assertSeeText(__('academics::filament.program.hub.levels'));
        $this->get(LevelFilamentResource::getUrl('view', ['record' => $level], panel: 'admin'))
            ->assertOk()->assertSeeText('LVL-HUB-01')->assertSeeText('CRS-HUB-01');
        $this->get(CourseFilamentResource::getUrl('view', ['record' => $course], panel: 'admin'))
            ->assertOk()->assertSeeText('CRS-HUB-01')->assertSeeText('CAT-HUB-01');

        $hub = app(AcademicAdministrationQueryService::class)->programHub(
            (string) $organization->id,
            (string) $program->id,
        );
        $this->assertSame('LVL-HUB-01', $hub['levels'][0]['code']);
        $this->assertSame('CRS-HUB-01', $hub['levels'][0]['courses'][0]['code']);
        $this->assertSame('CAT-HUB-01', $hub['categories'][0]['code']);
    }
}
