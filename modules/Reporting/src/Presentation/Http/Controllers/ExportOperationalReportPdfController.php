<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Reporting\Application\Services\OperationalReportCriteriaFactory;
use Modules\Reporting\Domain\Contracts\OperationalReportQuery;
use Modules\Reporting\Domain\Contracts\ReportPdfRenderer;
use Modules\Reporting\Domain\ValueObjects\OperationalReportCriteria;
use Modules\Reporting\Presentation\Http\Requests\ExportOperationalReportRequest;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Throwable;

final readonly class ExportOperationalReportPdfController
{
    public function __construct(
        private OperationalReportCriteriaFactory $criteriaFactory,
        private OperationalReportQuery $reports,
        private ReportPdfRenderer $pdf,
    ) {}

    public function __invoke(ExportOperationalReportRequest $request): Response
    {
        $startedAt = hrtime(true);
        $user = $request->user();

        if (!$user instanceof Authenticatable) {
            abort(401);
        }

        $criteria = $this->criteriaFactory->fromInput($request->validated(), $user);
        $report = $this->reports->run($criteria);
        $exportLimit = max(1, (int) config('reporting.operational.export_max_rows'));

        if ($report->limitExceeded || count($report->rows) > $exportLimit) {
            throw ValidationException::withMessages([
                'report' => __('reporting::operational.limit_exceeded'),
            ]);
        }

        $locale = app()->getLocale();
        $generatedAt = now('UTC')->timezone($criteria->timezone);
        $options = $this->reports->selectedOptions($criteria);
        $data = [
            'title' => __('reporting::operational.title'),
            'organizationName' => (string) config('app.name'),
            'generatedAt' => $generatedAt->format((string) config('reporting.operational.datetime_format')),
            'timezone' => $criteria->timezone,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'locale' => $locale,
            'periodLabel' => __('reporting::operational.period_label', [
                'from' => $criteria->fromDate,
                'until' => $criteria->untilDate,
            ]),
            'filterLabels' => $this->filterLabels($criteria, $options),
            'summary' => collect($report->summary)->mapWithKeys(
                static fn (int|float|string $value, string $key): array => [
                    __('reporting::operational.summary.'.$key) => $value,
                ],
            )->all(),
            'rows' => $report->rowsAsArray(),
        ];

        try {
            $bytes = $this->pdf->render(view('reporting::pdf.report', $data)->render());
        } catch (Throwable $exception) {
            Log::warning('reporting.operational_pdf_failed', [
                'organization_id' => $criteria->organizationId,
                'actor_id' => (string) $user->getAuthIdentifier(),
                'criteria_hash' => $criteria->cacheKey(),
                'exception' => $exception::class,
            ]);

            abort(500, __('reporting::messages.pdf_failed'));
        }

        Log::info('reporting.operational_pdf_exported', [
            'organization_id' => $criteria->organizationId,
            'actor_id' => (string) $user->getAuthIdentifier(),
            'criteria_hash' => $criteria->cacheKey(),
            'row_count' => count($report->rows),
            'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
        ]);

        $filename = sprintf('session-report-%s-%s.pdf', $criteria->fromDate, $criteria->untilDate);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @param array{students?: array<string, string>, teachers?: array<string, string>, groups?: array<string, string>, courses?: array<string, string>} $options
     * @return array<string, string>
     */
    private function filterLabels(OperationalReportCriteria $criteria, array $options): array
    {
        /** @var array<string, string> $labels */
        $labels = [];

        if ($criteria->statuses !== []) {
            $labels[(string) __('reporting::operational.filters.status')] = implode((string) __('reporting::operational.separators.list'), array_map(
                static fn (string $status): string => SessionStatus::tryFrom($status)?->label() ?? $status,
                $criteria->statuses,
            ));
        }

        if ($criteria->attendanceStatuses !== []) {
            $labels[(string) __('reporting::operational.filters.attendance_status')] = implode((string) __('reporting::operational.separators.list'), array_map(
                static fn (string $status): string => AttendanceStatus::tryFrom($status)?->label() ?? $status,
                $criteria->attendanceStatuses,
            ));
        }

        if ($criteria->sessionTypes !== []) {
            $labels[(string) __('reporting::operational.filters.session_type')] = implode((string) __('reporting::operational.separators.list'), array_map(
                static fn (string $type): string => (string) __('sessions::session_types.'.$type),
                $criteria->sessionTypes,
            ));
        }

        $this->appendSelectedOption($labels, 'student', $criteria->studentProfileId, $options['students'] ?? []);
        $this->appendSelectedOption($labels, 'teacher', $criteria->staffProfileId, $options['teachers'] ?? []);
        $this->appendSelectedOption($labels, 'original_teacher', $criteria->originalStaffProfileId, $options['teachers'] ?? []);
        $this->appendSelectedOption($labels, 'group', $criteria->groupId, $options['groups'] ?? []);
        $this->appendSelectedOption($labels, 'course', $criteria->courseId, $options['courses'] ?? []);

        if ($criteria->reportStatus !== null) {
            $labels[(string) __('reporting::operational.filters.report_status')]
                = (string) __('reporting::operational.report_status.'.$criteria->reportStatus);
        }

        if ($criteria->search !== '') {
            $labels[(string) __('reporting::operational.filters.search')] = $criteria->search;
        }

        return $labels;
    }

    /**
     * @param array<string, string> $labels
     * @param array<string, string> $options
     */
    private function appendSelectedOption(array &$labels, string $filter, ?string $selectedId, array $options): void
    {
        if ($selectedId !== null) {
            $labels[(string) __('reporting::operational.filters.'.$filter)]
                = $options[$selectedId] ?? (string) __('reporting::operational.selected_value');
        }
    }
}
