<?php

declare(strict_types=1);

use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Groups\Domain\Enums\GroupTeacherRole;

uses(RefreshDatabase::class);

it('builds repeatable client demo fixtures', function (): void {
    $this->seed();
    $this->seed(DemoDataSeeder::class);

    $first = demoFixtureCounts();

    expect($first['teachers'])->toBe(6)
        ->and($first['groups'])->toBe(4)
        ->and($first['sessions'])->toBeGreaterThan(0)
        ->and(DB::table('group_teachers')
            ->whereNotIn('role', array_column(GroupTeacherRole::cases(), 'value'))
            ->count())->toBe(0);

    $this->seed(DemoDataSeeder::class);

    expect(demoFixtureCounts())->toBe($first);
});

/**
 * @return array{teachers: int, groups: int, sessions: int}
 */
function demoFixtureCounts(): array
{
    $teacherUserIds = DB::table('users')
        ->where('email', 'like', '%@demo.eschool.local')
        ->pluck('id');

    $staffIds = DB::table('staff_profiles')
        ->whereIn('user_id', $teacherUserIds)
        ->pluck('id');

    $groupIds = DB::table('group_teachers')
        ->whereIn('staff_profile_id', $staffIds)
        ->pluck('group_id')
        ->unique();

    return [
        'teachers' => $teacherUserIds->count(),
        'groups' => $groupIds->count(),
        'sessions' => DB::table('sessions')->whereIn('group_id', $groupIds)->count(),
    ];
}
