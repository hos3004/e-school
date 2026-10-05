<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Console\SessionPayController;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Staff\Domain\Contracts\SessionPayCatalog;
use Modules\Staff\Domain\Contracts\TeacherRateResolver;
use Modules\Staff\Domain\Enums\ContractBasis;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherContract;
use Tests\TestCase;

final class SessionPaySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['console.enabled' => true]);
        foreach (['admin.panel.access', 'organizations.view', 'organizations.manage_settings'] as $permission) {
            Gate::define($permission, fn (): bool => true);
        }
    }

    public function test_rates_use_exact_minor_units_duration_type_and_historical_time(): void
    {
        $org = Organization::factory()->create();
        $actor = User::factory()->create(['organization_id' => $org->id]);
        $staff = StaffProfile::query()->create(['organization_id' => $org->id, 'user_id' => User::factory()->create(['organization_id' => $org->id])->id, 'staff_code' => 'T'.str()->random(8), 'employment_type' => 'part_time']);
        TeacherContract::query()->create(['organization_id' => $org->id, 'staff_profile_id' => $staff->id, 'basis' => ContractBasis::PerSession, 'effective_from' => '2026-09-01']);
        $this->actingAs($actor);
        $this->travelTo(CarbonImmutable::parse('2026-09-09 10:00:00', 'UTC'));
        $initial = SessionPayController::data($org->id);
        $this->post('/manage/settings/session-pay', [...$initial, 'reason' => 'Initial teacher pay'])->assertSessionHasNoErrors();
        $resolver = app(TeacherRateResolver::class);
        foreach ([['group', 35, 3125], ['individual', 25, 2500], ['individual', 40, 3350]] as [$type,$duration,$amount]) {
            self::assertSame($amount, $resolver->resolve($staff->id, CarbonImmutable::now(), sessionType: $type, durationMinutes: $duration)['money']->minorUnits);
        }
        self::assertNull($resolver->resolve($staff->id, CarbonImmutable::now(), sessionType: 'individual', durationMinutes: 35));
        self::assertSame([25, 40], app(SessionPayCatalog::class)->durations($org->id, 'individual'));
        $this->travelTo(CarbonImmutable::parse('2026-09-10 10:00:00', 'UTC'));
        $updated = SessionPayController::data($org->id);
        $updated['rates'][1]['price'] = '30.10';
        $this->post('/manage/settings/session-pay', [...$updated, 'reason' => 'Revised teacher pay'])->assertSessionHasNoErrors();
        self::assertSame(2500, $resolver->resolve($staff->id, CarbonImmutable::parse('2026-09-09 12:00:00', 'UTC'), sessionType: 'individual', durationMinutes: 25)['money']->minorUnits);
        self::assertSame(3010, $resolver->resolve($staff->id, CarbonImmutable::now(), sessionType: 'individual', durationMinutes: 25)['money']->minorUnits);
        $this->post('/manage/settings/session-pay', [...$updated, 'reason' => 'Stale write'])->assertSessionHasErrors('version');
        self::assertSame(2, DB::table('audit_log')->where('action', 'console.settings.session-pay')->count());
        self::assertSame([], app(SessionPayCatalog::class)->rates(Organization::factory()->create()->id));
    }

    public function test_invalid_duplicate_rates_and_missing_reason_are_rejected_and_permission_is_required(): void
    {
        $org = Organization::factory()->create();
        $actor = User::factory()->create(['organization_id' => $org->id]);
        $this->actingAs($actor);
        $data = SessionPayController::data($org->id);
        $data['rates'][] = $data['rates'][0];
        $this->post('/manage/settings/session-pay', $data)->assertSessionHasErrors(['reason', 'rates.3.duration_minutes']);
        $data['rates'][0]['price'] = '31.255';
        $this->post('/manage/settings/session-pay', [...$data, 'reason' => 'Invalid precision'])->assertSessionHasErrors('rates.0.price');
        Gate::define('organizations.manage_settings', fn (): bool => false);
        $this->post('/manage/settings/session-pay', [...SessionPayController::data($org->id), 'reason' => 'Unauthorized'])->assertForbidden();
        self::assertSame([], app(SessionPayCatalog::class)->rates($org->id));
    }
}
