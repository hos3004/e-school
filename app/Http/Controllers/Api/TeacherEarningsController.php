<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payroll\Domain\Contracts\TeacherEarningsQueries;
use Modules\Payroll\Domain\ValueObjects\TeacherPeriodEarnings;

/**
 * كشف أجر المعلم للموبايل — مرآة Portal\TeacherEarningsController بالضبط:
 * نفس TeacherEarningsQueries المُعلَن (Payroll موديول مختوم، لا قراءة
 * مباشرة لجداوله من هنا)، عرض فقط بلا أي إجراء صرف.
 *
 * الحماية من TeacherFinancialVisibilityGate تلقائية عبر middleware
 * can:payroll.view في routes/api.php — نفس الحارس المسجَّل عالميًا
 * (Gate::before) الذي يحمي مسار الويب، لا كود إضافي هنا.
 */
final class TeacherEarningsController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
        private readonly TeacherEarningsQueries $earnings,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $staffProfileId = $this->data->staffProfileId(
            (string) $request->user()?->getAuthIdentifier(),
            $organizationId,
        );

        $periods = $staffProfileId === null
            ? []
            : $this->earnings->periodsFor($organizationId, $staffProfileId);

        return response()->json([
            'has_profile' => $staffProfileId !== null,
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
