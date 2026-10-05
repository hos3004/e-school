<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Groups\Database\Seeders\GroupsSeeder;
use Modules\Students\Database\Seeders\StudentsSeeder;

it('seeds group relationships from existing profiles without inventing foreign ids', function (): void {
    $this->seed(StudentsSeeder::class);

    expect(DB::table('staff_profiles')->count())->toBe(0);

    $this->seed(GroupsSeeder::class);
    $this->seed(GroupsSeeder::class);

    expect(DB::table('groups')->count())->toBe(3)
        ->and(DB::table('group_teachers')->count())->toBe(0)
        ->and(DB::table('group_memberships')->count())->toBe(15)
        ->and(
            DB::table('group_memberships')
                ->leftJoin('student_profiles', 'student_profiles.id', '=', 'group_memberships.student_profile_id')
                ->whereNull('student_profiles.id')
                ->count(),
        )->toBe(0);
});
