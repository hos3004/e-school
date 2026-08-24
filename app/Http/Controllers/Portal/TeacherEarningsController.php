<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Payroll\Domain\Contracts\TeacherEarningsQueries;
use Modules\Payroll\Domain\ValueObjects\TeacherPeriodEarnings;

/**
 * كشف أجر المعلم (ADR-017).
 *
 * القراءة عبر عقد Payroll المعلن لا عبر جداوله: الموديول مختوم، وبقية
 * البوابة تقرأ بـDB مباشرة لأن جداولها غير مختومة — أما المال فلا.
 *
 * المنصة هنا **تحسب وتعرض** ولا **تدفع**: لا يوجد في هذه الصفحة أي إجراء
 * تحويل أو صرف، وفوترة الطلاب وبوابات الدفع تبقى خارج المرحلة.
 */
final class TeacherEarningsController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
        private readonly TeacherEarningsQueries $earnings,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $organizationId = (string) $user?->getAttribute('organization_id');
        $staffProfileId = $this->data->staffProfileId(
            (string) $user?->getAuthIdentifier(),
            $organizationId,
        );

        $periods = $staffProfileId === null
            ? []
            : $this->earnings->periodsFor($organizationId, $staffProfileId);

        return Inertia::render('Teacher/Earnings', [
            'hasProfile' => $staffProfileId !== null,
            'periods' => array_map(
                static fn (TeacherPeriodEarnings $period): array => [
                    'id' => $period->periodId,
                    'year' => $period->year,
                    'month' => $period->month,
                    'status' => $period->status,
                    'currency' => $period->currency,
                    'earningsMinorUnits' => $period->earningsMinorUnits,
                    'deductionsMinorUnits' => $period->deductionsMinorUnits,
                    'adjustmentsMinorUnits' => $period->adjustmentsMinorUnits,
                    'netMinorUnits' => $period->netMinorUnits,
                    'sessionsCount' => $period->sessionsCount,
                    'entries' => $period->entries,
                    'adjustments' => $period->adjustments,
                ],
                $periods,
            ),
        ]);
    }
}
