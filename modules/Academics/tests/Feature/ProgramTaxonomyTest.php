<?php

declare(strict_types=1);

namespace Modules\Academics\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academics\Domain\Enums\ProgramType;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Academics\Domain\Models\ProgramCategory;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class ProgramTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_can_belong_to_multiple_categories(): void
    {
        $orgId = Fixtures::organizationId();

        $program = Program::create([
            'organization_id' => $orgId,
            'code' => 'PROG-1',
            'name' => ['ar' => 'برنامج 1'],
            'program_type' => ProgramType::Ongoing,
            'default_session_minutes' => 60,
            'currency' => 'EGP',
        ]);

        $level = Level::create([
            'program_id' => $program->id,
            'code' => 'LVL-1',
            'name' => ['ar' => 'مستوى 1'],
        ]);

        $course = Course::create([
            'organization_id' => $orgId,
            'level_id' => $level->id,
            'code' => 'CRS-1',
            'name' => ['ar' => 'مادة 1'],
        ]);

        $cat1 = ProgramCategory::create([
            'organization_id' => $orgId,
            'code' => 'CAT-1',
            'name' => ['ar' => 'تصنيف 1'],
        ]);

        $cat2 = ProgramCategory::create([
            'organization_id' => $orgId,
            'code' => 'CAT-2',
            'name' => ['ar' => 'تصنيف 2'],
        ]);

        $course->categories()->attach([$cat1->id, $cat2->id]);

        $this->assertCount(2, $course->fresh()->categories);
        $this->assertTrue($cat1->courses()->where('courses.id', $course->id)->exists());
        $this->assertTrue($cat2->courses()->where('courses.id', $course->id)->exists());
    }

    public function test_fixed_duration_program_without_end_date_fails(): void
    {
        $this->expectException(QueryException::class);

        Program::create([
            'organization_id' => Fixtures::organizationId(),
            'code' => 'FIX-INVALID',
            'name' => ['ar' => 'برنامج محدد المدة خاطئ'],
            'program_type' => ProgramType::FixedDuration,
            'start_date' => '2026-01-01',
            'end_date' => null, // Should fail PostgreSQL check constraint
            'default_session_minutes' => 60,
            'currency' => 'EGP',
        ]);
    }
}
