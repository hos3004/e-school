<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Students\Domain\Models\StudentProfile;
use Tests\TestCase;

final class ProfileCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(GeographySeeder::class);
    }

    public function test_incomplete_students_and_teachers_cannot_bypass_profile_by_web_or_api(): void
    {
        foreach (['student', 'teacher'] as $kind) {
            $user = $this->account($kind);
            $this->actingAs($user)->get('/'.$kind.'/profile')->assertRedirect('/profile/complete');
            $this->getJson('/api/identity/me')->assertForbidden()->assertJsonPath('code', 'profile_completion_required');
            $this->get('/profile/complete')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Learning/CompleteProfile')->where('required', true));
            $this->post('/logout')->assertRedirect();
        }
    }

    public function test_completion_saves_self_only_and_preserves_status_while_clearing_old_verification(): void
    {
        foreach (['student', 'teacher'] as $kind) {
            $user = $this->account($kind);
            $other = User::factory()->create();
            $user->forceFill(['email_verified_at' => now(), 'phone_verified_at' => now()])->save();
            $this->actingAs($user)->post('/profile/complete', [...$this->payload(), 'email' => $kind.'@school.test', 'user_id' => $other->id, 'status' => 'suspended'])->assertSessionHasNoErrors()->assertRedirect();
            $saved = $user->fresh();
            self::assertNotNull($saved->profile_completed_at);
            self::assertNull($saved->email_verified_at);
            self::assertNull($saved->phone_verified_at);
            self::assertSame('active', $saved->status->value);
            self::assertSame($kind.'@school.test', $saved->email);
            self::assertSame($other->email, $other->fresh()->email);
            $table = $kind === 'student' ? 'student_profiles' : 'staff_profiles';
            $this->assertDatabaseHas($table, ['user_id' => $user->id, 'city' => 'Cairo']);
            $this->actingAs($saved)->get('/'.$kind.'/profile')->assertOk();
        }
        self::assertSame(2, DB::table('audit_log')->where('action', 'identity.profile_completed')->count());
    }

    public function test_temporary_email_wrong_region_invalid_phone_and_unconfirmed_form_are_rejected(): void
    {
        $user = $this->account('student');
        $payload = $this->payload();
        $otherCountry = collect(app(GeographyQueries::class)->countries())->first(fn ($c) => $c->id !== $payload['country_id']);
        $this->actingAs($user)->post('/profile/complete', [...$payload, 'email' => 's1001@accounts.telecourse.invalid', 'phone' => '123', 'country_id' => $otherCountry->id, 'confirmed' => false])
            ->assertSessionHasErrors(['email', 'phone', 'region_id', 'confirmed']);
        self::assertNull($user->fresh()->profile_completed_at);
        $this->get('/student/profile')->assertRedirect('/profile/complete');
    }

    public function test_accounts_without_student_or_teacher_profile_are_not_forced_and_cannot_complete_another_profile(): void
    {
        $admin = User::factory()->create(['profile_completed_at' => null]);
        $this->actingAs($admin)->get('/profile/complete')->assertForbidden();
        $this->get('/about')->assertOk();
        $this->post('/profile/complete', $this->payload())->assertForbidden();
    }

    public function test_manual_region_is_required_when_no_region_is_selected_and_is_saved(): void
    {
        $user = $this->account('teacher');
        $payload = [...$this->payload(), 'region_id' => null];
        $this->actingAs($user)->post('/profile/complete', $payload)->assertSessionHasErrors('region_name');
        $this->post('/profile/complete', [...$payload, 'region_name' => 'الجيزة'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('staff_profiles', ['user_id' => $user->id, 'region_id' => null, 'region_name' => 'الجيزة']);
        $this->actingAs($user->fresh())->get('/profile/complete')->assertOk()->assertInertia(fn (Assert $page) => $page->where('profile.region_name', 'الجيزة')->where('required', false));
    }

    private function account(string $kind): User
    {
        $user = User::factory()->create(['profile_completed_at' => null]);
        if ($kind === 'student') {
            StudentProfile::factory()->create(['organization_id' => $user->organization_id, 'user_id' => $user->id]);
        } else {
            StaffProfile::query()->create(['organization_id' => $user->organization_id, 'user_id' => $user->id, 'staff_code' => 'T'.str()->random(8), 'employment_type' => 'part_time']);
        }

        return $user->fresh();
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $geo = app(GeographyQueries::class);
        $country = $geo->findCountryByIso2('EG');
        self::assertNotNull($country);

        return ['name' => 'Profile Owner', 'email' => 'owner@school.test', 'phone' => '+20 1012345678', 'country_id' => $country->id,
            'region_id' => $geo->regionsOf($country->id)[0]->id, 'city' => 'Cairo', 'date_of_birth' => '1990-01-02', 'gender' => 'female', 'timezone' => 'Europe/Istanbul', 'confirmed' => true];
    }
}
