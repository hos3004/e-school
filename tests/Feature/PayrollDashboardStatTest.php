<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Organization\Domain\Models\Organization;
use Tests\TestCase;

/**
 * بطاقة «مستحقات الشهر» كانت تقرأ 0.00 مهما بلغت المستحقات.
 *
 * السبب أن الدفتر append-only فـ`PayrollEntry::$timestamps = false`، ويبقى عمود
 * `created_at` فارغًا في كل قيدة، بينما كانت البطاقة ترشّح
 * `created_at >= بداية الشهر` — شرطٌ لا تحققه قيدة واحدة أبدًا. العطب صامت:
 * لا استثناء ولا سجل، رقم صفري يبدو صحيحًا فوق دفتر عامر.
 *
 * الترشيح الآن على `payroll_period_id`، وهي أيضًا الدلالة الصحيحة: حصة أغسطس
 * تُعتمد في سبتمبر وتبقى مستحقًا أغسطسيًّا.
 */
final class PayrollDashboardStatTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_entry_without_created_at_still_counts_in_this_month(): void
    {
        $organization = Organization::factory()->create();
        $organizationId = (string) $organization->getKey();

        $now = CarbonImmutable::now('UTC');
        $periodId = (string) Str::ulid();

        DB::table('payroll_periods')->insert([
            'id' => $periodId,
            'organization_id' => $organizationId,
            'year' => $now->year,
            'month' => $now->month,
            'starts_on' => $now->startOfMonth()->toDateString(),
            'ends_on' => $now->endOfMonth()->toDateString(),
            'status' => 'open',
            'totals' => json_encode([], JSON_THROW_ON_ERROR),
        ]);

        DB::table('payroll_entries')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'payroll_period_id' => $periodId,
            'staff_profile_id' => (string) Str::ulid(),
            'teacher_contract_id' => (string) Str::ulid(),
            'entry_type' => 'session_earning',
            'outcome_key' => 'completed',
            'amount' => 15000,
            'currency' => 'EGP',
            'rate_snapshot' => json_encode(['amount' => 15000], JSON_THROW_ON_ERROR),
            'status' => 'recorded',
            // كما يكتبها الدفتر فعلًا: بلا طابع زمني.
            'created_at' => null,
        ]);

        $periodIds = DB::table('payroll_periods')
            ->where('organization_id', $organizationId)
            ->where('year', $now->year)
            ->where('month', $now->month)
            ->pluck('id');

        $net = (int) DB::table('payroll_entries')
            ->where('organization_id', $organizationId)
            ->whereIn('payroll_period_id', $periodIds)
            ->sum('amount');

        $this->assertSame(
            15000,
            $net,
            'قيدة الشهر لم تُحتسب — عادت البطاقة إلى الترشيح على created_at الفارغ.',
        );

        $byCreatedAt = (int) DB::table('payroll_entries')
            ->where('organization_id', $organizationId)
            ->where('created_at', '>=', $now->startOfMonth())
            ->sum('amount');

        $this->assertSame(
            0,
            $byCreatedAt,
            'إن صار created_at يُملأ فراجع البطاقة: الترشيح عليه لم يعد يُسقط القيود.',
        );
    }
}
