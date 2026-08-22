<?php

declare(strict_types=1);

namespace Modules\Staff\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\AccessControl\Tests\Support\ApiUser;
use Tests\TestCase;

final class StaffProfileAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_index_only_returns_profiles_from_the_authenticated_users_organization(): void
    {
        Gate::define('staff.view.any', static fn (): bool => true);

        $organizationId = $this->createOrganization('staff-index-owner');
        $otherOrganizationId = $this->createOrganization('staff-index-other');
        $profileId = $this->createStaffProfile($organizationId, 'OWNER');
        $otherProfileId = $this->createStaffProfile($otherOrganizationId, 'OTHER');

        $response = $this->actingAs($this->actor($organizationId))
            ->getJson('/api/staff/profiles');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $profileId)
            ->assertJsonMissing(['id' => $otherProfileId]);
    }

    public function test_show_forbids_a_profile_from_another_organization(): void
    {
        Gate::define('staff.view.any', static fn (): bool => true);

        $organizationId = $this->createOrganization('staff-show-owner');
        $otherOrganizationId = $this->createOrganization('staff-show-other');
        $otherProfileId = $this->createStaffProfile($otherOrganizationId, 'SHOW-OTHER');

        $this->actingAs($this->actor($organizationId))
            ->getJson('/api/staff/profiles/'.$otherProfileId)
            ->assertForbidden();
    }

    public function test_store_forbids_creating_a_profile_for_another_organization(): void
    {
        Gate::define('staff.contract.update', static fn (): bool => true);

        $organizationId = $this->createOrganization('staff-store-owner');
        $otherOrganizationId = $this->createOrganization('staff-store-other');

        $this->actingAs($this->actor($organizationId))
            ->postJson('/api/staff/profiles', [
                'organization_id' => $otherOrganizationId,
                'user_id' => (string) Str::ulid(),
                'staff_code' => 'CROSS-'.Str::upper(Str::random(8)),
                'employment_type' => 'part_time',
                'gender' => 'female',
                'country_id' => (string) Str::ulid(),
                'region_id' => (string) Str::ulid(),
            ])
            ->assertForbidden();
    }

    public function test_store_forbids_linking_a_user_from_another_organization(): void
    {
        Gate::define('staff.contract.update', static fn (): bool => true);

        $organizationId = $this->createOrganization('staff-target-owner');
        $otherOrganizationId = $this->createOrganization('staff-target-other');
        $otherUserId = $this->createUser($otherOrganizationId);

        $this->actingAs($this->actor($organizationId))
            ->postJson('/api/staff/profiles', [
                'organization_id' => $organizationId,
                'user_id' => $otherUserId,
                'staff_code' => 'CROSS-USER-'.Str::upper(Str::random(8)),
                'employment_type' => 'part_time',
                'gender' => 'female',
                'country_id' => (string) Str::ulid(),
                'region_id' => (string) Str::ulid(),
            ])
            ->assertForbidden();
    }

    private function actor(string $organizationId): ApiUser
    {
        return (new ApiUser((string) Str::ulid()))->forceFill([
            'organization_id' => $organizationId,
        ]);
    }

    private function createOrganization(string $slug): string
    {
        $id = (string) Str::ulid();

        DB::table('organizations')->insert([
            'id' => $id,
            'name' => json_encode(['ar' => 'مؤسسة اختبار', 'en' => 'Test organization'], JSON_THROW_ON_ERROR),
            'slug' => $slug.'-'.strtolower(substr($id, -8)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function createStaffProfile(string $organizationId, string $codePrefix): string
    {
        $userId = $this->createUser($organizationId);
        $profileId = (string) Str::ulid();
        $suffix = strtolower(substr($userId, -8));

        DB::table('staff_profiles')->insert([
            'id' => $profileId,
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'staff_code' => $codePrefix.'-'.strtoupper($suffix),
            'employment_type' => 'part_time',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $profileId;
    }

    private function createUser(string $organizationId): string
    {
        $userId = (string) Str::ulid();
        $suffix = strtolower(substr($userId, -8));

        DB::table('users')->insert([
            'id' => $userId,
            'organization_id' => $organizationId,
            'name' => 'Staff '.$suffix,
            'email' => 'staff.'.$suffix.'@example.test',
            'username' => 'staff.'.$suffix,
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $userId;
    }
}
