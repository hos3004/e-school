<?php

declare(strict_types=1);
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Scheduling\Domain\Contracts\IndividualTeachingAssignments;
use Modules\Scheduling\Domain\Models\PendingTeachingAssignment;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);
it('shows pending students only to their assigned teacher and removes archived links without creating sessions', function (): void {
    $org = Fixtures::organizationId();
    $teacher = Fixtures::staffProfileId();
    $student = Fixtures::studentProfileId();
    $other = Fixtures::staffProfileId();
    $assignment = PendingTeachingAssignment::query()->create([
        'organization_id' => $org, 'staff_profile_id' => $teacher, 'student_profile_id' => $student, 'course_id' => Fixtures::courseId(),
        'created_by' => Fixtures::userId(), 'session_type' => 'group', 'duration_minutes' => 35, 'reason' => 'Approved timetable awaiting time',
    ]);
    $query = app(IndividualTeachingAssignments::class);
    expect($query->activeForTeacher($org, $other))->toBeEmpty();
    expect($query->activeForTeacher('unrelated-organization', $teacher))->toBeEmpty();
    $result = $query->activeForTeacher($org, $teacher);
    expect($result)->toHaveCount(1)->and($result[0]->awaitingSchedule)->toBeTrue()->and($result[0]->studentProfileId)->toBe($student)->and($result[0]->sessionType)->toBe('group');
    $this->assertDatabaseCount('sessions', 0);
    $assignment->delete();
    expect($query->activeForTeacher($org, $teacher))->toBeEmpty();
});
