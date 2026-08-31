<?php

declare(strict_types=1);

use Database\Seeders\DemoDataSeeder;
use Database\Seeders\PortalLiveSessionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('builds repeatable role-complete portal acceptance fixtures', function (): void {
    $this->seed();
    $this->seed(DemoDataSeeder::class);
    $this->seed(PortalLiveSessionSeeder::class);
    $this->seed(PortalLiveSessionSeeder::class);

    $emails = [
        PortalLiveSessionSeeder::TEACHER_EMAIL,
        PortalLiveSessionSeeder::STUDENT_EMAIL,
        PortalLiveSessionSeeder::GUARDIAN_EMAIL,
    ];

    expect(DB::table('users')->whereIn('email', $emails)->count())->toBe(3)
        ->and(DB::table('guardian_links')
            ->join('guardian_profiles', 'guardian_profiles.id', '=', 'guardian_links.guardian_profile_id')
            ->join('users', 'users.id', '=', 'guardian_profiles.user_id')
            ->where('users.email', PortalLiveSessionSeeder::GUARDIAN_EMAIL)
            ->whereNull('guardian_links.deleted_at')
            ->count())->toBe(1)
        ->and(DB::table('sessions')->where('notes', PortalLiveSessionSeeder::SESSION_MARKER)->count())->toBe(1);
});
