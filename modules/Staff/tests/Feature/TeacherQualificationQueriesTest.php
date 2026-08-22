<?php

declare(strict_types=1);

namespace Modules\Staff\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Staff\Domain\Contracts\TeacherQualificationQueries;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class TeacherQualificationQueriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_qualification_queries_returns_correct_ids_and_qualification_status(): void
    {
        $staffId = Fixtures::staffProfileId();
        $courseId = (string) Str::ulid();
        $qualifiedBy = Fixtures::userId();

        DB::table('staff_profiles')
            ->where('id', $staffId)
            ->update(['gender' => 'female']);

        DB::table('teacher_courses')->insert([
            'id' => (string) Str::ulid(),
            'staff_profile_id' => $staffId,
            'course_id' => $courseId,
            'qualified_at' => now(),
            'qualified_by' => $qualifiedBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var TeacherQualificationQueries $queries */
        $queries = app(TeacherQualificationQueries::class);

        $this->assertInstanceOf(TeacherQualificationQueries::class, $queries);
        $this->assertTrue($queries->isQualified($staffId, $courseId));
        $this->assertFalse($queries->isQualified($staffId, (string) Str::ulid()));
        $this->assertSame([$staffId], $queries->qualifiedTeacherIdsForCourse($courseId));
        $this->assertSame('female', $queries->genderOf($staffId));
        $this->assertNull($queries->genderOf((string) Str::ulid()));
    }
}
