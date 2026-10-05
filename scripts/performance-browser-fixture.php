<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Models\User;
use Modules\Students\Domain\Models\StudentProfile;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (!$app->environment('local') || config('database.connections.pgsql.host') !== 'eschool-performance-db'
    || DB::selectOne('select current_database() as name')->name !== 'eschool_performance_review') {
    throw new RuntimeException('Only the isolated performance review database is allowed.');
}
foreach (['student', 'teacher', 'platform_admin'] as $role) {
    $user = User::factory()->create([
        'email' => 'performance-'.$role.'@school.invalid',
        'username' => 'performance.'.$role,
        'password' => Hash::make('Performance-QA!2026'),
    ]);
    if ($role === 'student') {
        StudentProfile::factory()->create(['organization_id' => $user->organization_id, 'user_id' => $user->id]);
    }
    if ($role === 'teacher') {
        DB::table('staff_profiles')->insert([
            'id' => (string) Str::ulid(), 'organization_id' => $user->organization_id,
            'user_id' => $user->id, 'staff_code' => 'PERFORMANCE-QA', 'employment_type' => 'part_time',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    $roleId = DB::table('roles')->whereNull('organization_id')->where('guard_name', 'web')->where('name', $role)->value('id');
    DB::table('model_has_roles')->insert([
        'role_id' => $roleId, 'model_type' => $user::class, 'model_id' => $user->id,
    ]);
}
echo "Created isolated performance fixtures.\n";
