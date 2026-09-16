<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Console\Support\ProgramSessionReportPresenter;
use App\Http\Controllers\Console\Support\ReportDateRange;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Organization\Domain\Contracts\SchoolClockQueries;
use Modules\Reporting\Domain\Contracts\ProgramSessionReportDigestQueries;
use Modules\Reporting\Domain\ValueObjects\ProgramSessionReportDigestData;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

/**
 * لوحة تقارير الحصص المجمَّعة: تقارير اليوم عبر كل المدرسة، وأرشيف شهري
 * قابل للتصفح، ونافذة تاريخ مخصَّصة — تُبنى على نفس عقد القراءة الذي
 * يستهلكه أمر الإرسال الشهري (ProgramSessionReportDigestQueries) كي تعرض
 * الشاشة نفس الحقيقة التي ستُرسَل بالبريد، لا نسخة موازية منها.
 */
final class ProgramSessionReportsController extends Controller
{
    public function __construct(
        private readonly ProgramSessionReportDigestQueries $digest,
        private readonly AcademicCatalogQueries $catalog,
        private readonly StudentDirectoryQueries $students,
        private readonly SchoolClockQueries $clock,
        private readonly ProgramSessionReportPresenter $presenter,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);
        $organizationId = (string) $user->getAttribute('organization_id');
        $timezone = (string) $this->clock->forOrganization($organizationId)['timezone'];

        $filters = $request->validate([
            'view' => ['nullable', 'in:today,month,custom'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'program_id' => ['nullable', 'string'],
        ]);

        $view = $filters['view'] ?? (($filters['from'] ?? null) !== null ? 'custom' : 'today');
        $range = $view === 'month'
            ? ReportDateRange::resolveMonth($filters['from'] ?? null, $filters['to'] ?? null, $filters['month'] ?? null, $timezone)
            : ReportDateRange::resolve($filters['from'] ?? null, $filters['to'] ?? null, $timezone);

        $programId = ($filters['program_id'] ?? '') !== '' ? $filters['program_id'] : null;
        $rows = $this->digest->forOrganizationInRange($organizationId, $range->fromUtc, $range->untilUtcExclusive, $programId);

        $programIds = array_values(array_unique(array_map(
            static fn (ProgramSessionReportDigestData $row): string => $row->programId,
            $rows,
        )));
        $programsById = $this->catalog->programsByIds($organizationId, $programIds);
        $studentIds = array_values(array_unique(array_map(
            static fn (ProgramSessionReportDigestData $row): string => $row->studentProfileId,
            $rows,
        )));
        $studentNames = $this->students->namesForProfiles($organizationId, $studentIds);

        $groups = array_map(
            fn (array $group): array => [
                ...$group,
                'programUrl' => route('console.reports.session-reports.program', [
                    'program' => $group['programId'],
                    'from' => $range->fromDate,
                    'to' => $range->toDate,
                ]),
            ],
            $this->presenter->byProgramThenStudent($rows, $programsById, $studentNames),
        );

        return Inertia::render('Console/Reports/SessionReports', [
            'view' => $view,
            'filters' => [
                'from' => $range->fromDate,
                'to' => $range->toDate,
                'month' => $filters['month'] ?? substr($range->fromDate, 0, 7),
                'program_id' => $programId,
            ],
            'timezone' => $timezone,
            'programs' => array_map(
                static fn ($program): array => ['id' => $program->id, 'name' => $program->name, 'code' => $program->code],
                $this->catalog->programs($organizationId),
            ),
            'groups' => $groups,
            'totals' => [
                'programs' => count($programIds),
                'students' => count($studentIds),
                'reports' => count($rows),
            ],
            'settingsUrl' => $request->user()?->can('reporting.settings.manage')
                ? route('console.reports.session-reports.settings.edit')
                : null,
        ]);
    }

    public function program(Request $request, string $program): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);
        $organizationId = (string) $user->getAttribute('organization_id');
        $timezone = (string) $this->clock->forOrganization($organizationId)['timezone'];

        $programs = $this->catalog->programsByIds($organizationId, [$program]);
        $programData = $programs[$program] ?? null;
        abort_if($programData === null, 404);

        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $range = ReportDateRange::resolveMonth($filters['from'] ?? null, $filters['to'] ?? null, $filters['month'] ?? null, $timezone);

        $rows = $this->digest->forOrganizationInRange($organizationId, $range->fromUtc, $range->untilUtcExclusive, $program);
        $studentIds = array_values(array_unique(array_map(
            static fn (ProgramSessionReportDigestData $row): string => $row->studentProfileId,
            $rows,
        )));
        $studentNames = $this->students->namesForProfiles($organizationId, $studentIds);

        return Inertia::render('Console/Reports/ProgramProfile', [
            'program' => ['id' => $programData->id, 'name' => $programData->name, 'code' => $programData->code],
            'filters' => [
                'from' => $range->fromDate,
                'to' => $range->toDate,
            ],
            'timezone' => $timezone,
            'students' => $this->presenter->byStudent($rows, $studentNames),
            'totals' => ['students' => count($studentIds), 'reports' => count($rows)],
            'backUrl' => route('console.reports.session-reports.index'),
        ]);
    }
}
