<?php

declare(strict_types=1);

use Database\Seeders\IndividualQuranCourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Models\StaffProfile;

uses(RefreshDatabase::class);

it('adds the individual Quran course once and qualifies existing Quran teachers', function (): void {
    $organization = Organization::factory()->create(['slug' => 'telecourse']);
    $actor = User::factory()->inOrganization((string) $organization->id)->create(['status' => 'active']);
    $teacherUser = User::factory()->inOrganization((string) $organization->id)->create(['status' => 'active']);
    $program = Program::factory()->create(['organization_id' => $organization->id]);
    $level = Level::factory()->create(['program_id' => $program->id]);
    $source = Course::factory()->create([
        'organization_id' => $organization->id,
        'level_id' => $level->id,
        'code' => 'C-QURAN-101',
        'session_mode' => SessionMode::Group,
    ]);
    $teacher = StaffProfile::query()->create([
        'organization_id' => $organization->id,
        'user_id' => $teacherUser->id,
        'staff_code' => 'T-QURAN',
        'employment_type' => EmploymentType::Contractor,
        'gender' => StaffGender::Female,
        'hired_at' => '2026-01-01',
    ]);
    DB::table('teacher_courses')->insert([
        'id' => (string) Str::ulid(),
        'staff_profile_id' => $teacher->id,
        'course_id' => $source->id,
        'qualified_at' => now('UTC'),
        'qualified_by' => $actor->id,
        'created_at' => now('UTC'),
        'updated_at' => now('UTC'),
    ]);

    $this->seed(IndividualQuranCourseSeeder::class);
    $this->seed(IndividualQuranCourseSeeder::class);

    $course = Course::query()->where('code', 'C-QURAN-IND')->firstOrFail();
    expect($course->session_mode)->toBe(SessionMode::Individual)
        ->and($course->default_duration_minutes)->toBe(35)
        ->and(Course::query()->where('code', 'C-QURAN-IND')->count())->toBe(1)
        ->and(DB::table('teacher_courses')
            ->where('staff_profile_id', $teacher->id)
            ->where('course_id', $course->id)
            ->count())->toBe(1);
});
