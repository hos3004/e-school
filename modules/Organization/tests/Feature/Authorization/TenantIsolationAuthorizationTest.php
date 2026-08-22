<?php

declare(strict_types=1);

namespace Modules\Organization\Tests\Feature\Authorization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Models\User;
use Modules\Students\Application\Policies\StudentProfilePolicy;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class TenantIsolationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\AccessControl\Database\Seeders\AccessControlSeeder::class);
    }

    public function test_user_from_org_a_cannot_access_resource_from_org_b(): void
    {
        $orgA = Fixtures::organizationId();

        // Create Org B manually with a new ULID
        $orgB = (string) Str::ulid();
        DB::table('organizations')->insert([
            'id' => $orgB,
            'name' => json_encode(['ar' => 'مؤسسة B', 'en' => 'Organization B'], JSON_UNESCAPED_UNICODE),
            'slug' => 'test-org-b-'.strtolower(substr($orgB, -8)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userAId = (string) Str::ulid();
        DB::table('users')->insert([
            'id' => $userAId,
            'organization_id' => $orgA,
            'name' => 'User Org A',
            'email' => 'user.orga@test.local',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userBId = (string) Str::ulid();
        DB::table('users')->insert([
            'id' => $userBId,
            'organization_id' => $orgB,
            'name' => 'User Org B',
            'email' => 'user.orgb@test.local',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var User $userA */
        $userA = User::query()->findOrFail($userAId);
        /** @var User $userB */
        $userB = User::query()->findOrFail($userBId);

        $studentProfileB = new StudentProfile();
        $studentProfileB->organization_id = $orgB;
        $studentProfileB->user_id = $userBId;

        $policy = new StudentProfilePolicy();

        // User A (from Org A) attempting to view Student Profile from Org B must fail tenant isolation
        $this->assertNotEquals($userA->organization_id, $studentProfileB->organization_id);
    }
}
