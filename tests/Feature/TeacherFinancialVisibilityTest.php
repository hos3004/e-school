<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\TeacherSessionCounts;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Staff\Domain\Models\StaffProfile;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class TeacherFinancialVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_change_one_teachers_visibility_with_reason_and_scope(): void
    {
        config(['console.enabled' => true]);
        $org = Fixtures::organizationId();
        $profile = StaffProfile::query()->findOrFail(Fixtures::staffProfileId());
        $other = StaffProfile::query()->findOrFail(Fixtures::staffProfileId());
        $admin = User::factory()->inOrganization($org)->create();
        Gate::define('admin.panel.access', fn (User $u): bool => $u->id === $admin->id);
        Gate::define('staff.contract.update', fn (User $u): bool => $u->id === $admin->id);
        Gate::define('payroll.view', static fn (): bool => true);
        $url = '/manage/teachers/'.$profile->id.'/financial-visibility';
        $this->flushSession();
        $this->actingAs($admin, 'web')->put($url, ['financials_visible' => false])->assertSessionHasErrors('reason');
        $this->put($url, ['financials_visible' => false, 'reason' => 'Display lesson counts only'])->assertSessionHasNoErrors()->assertRedirect();
        expect($profile->fresh()->financials_visible)->toBeFalse()->and($other->fresh()->financials_visible)->toBeTrue();
        $this->assertDatabaseHas('audit_log', ['action' => 'staff.financial_visibility_changed', 'auditable_id' => $profile->id, 'actor_id' => $admin->id]);
        expect($admin->can('payroll.view'))->toBeTrue();
        $this->flushSession();
        $this->actingAs(User::query()->findOrFail($profile->user_id), 'web')->put($url, ['financials_visible' => true, 'reason' => 'Self change'])->assertForbidden();
        $this->flushSession();
        $this->actingAs($admin, 'web')->put($url, ['financials_visible' => true, 'reason' => 'Restore statement'])->assertRedirect();
        expect($profile->fresh()->financials_visible)->toBeTrue();
        $foreign = User::factory()->inOrganization(Organization::factory()->create()->id)->create();
        DB::table('staff_profiles')->where('id', $other->id)->update(['organization_id' => $foreign->organization_id]);
        $this->put('/manage/teachers/'.$other->id.'/financial-visibility', ['financials_visible' => false, 'reason' => 'Wrong organization'])->assertNotFound();
        $this->assertDatabaseCount('payroll_entries', 0);
    }

    public function test_counts_use_teacher_scope_and_local_month_without_payroll_entries(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-09T10:00:00Z'));
        $org = Fixtures::organizationId();
        $staff = Fixtures::staffProfileId();
        $other = Fixtures::staffProfileId();
        $course = Fixtures::courseId();
        foreach ([[$staff, '2026-08-31T21:30:00Z', 'completed'], [$staff, '2026-08-31T19:00:00Z', 'completed'], [$staff, '2026-09-30T21:00:00Z', 'completed'], [$staff, '2026-09-15T10:00:00Z', 'scheduled'], [$other, '2026-09-16T10:00:00Z', 'scheduled']] as [$id,$start,$status]) {
            Session::factory()->create(['organization_id' => $org, 'staff_profile_id' => $id, 'course_id' => $course, 'session_type' => 'individual', 'scheduled_start' => $start, 'scheduled_end' => CarbonImmutable::parse($start)->addMinutes(25), 'status' => SessionStatus::from($status)]);
        }
        $counts = app(TeacherSessionCounts::class)->forTeacher($org, $staff, 'Europe/Istanbul');
        expect($counts)->toBe(['month' => '2026-09', 'upcoming' => 1, 'completed' => 1, 'cancelled' => 0]);
        $this->assertDatabaseCount('payroll_entries', 0);
    }
}
