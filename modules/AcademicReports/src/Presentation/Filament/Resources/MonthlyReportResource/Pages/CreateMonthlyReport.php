<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Presentation\Filament\Resources\MonthlyReportResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\AcademicReports\Application\Actions\DraftMonthlyReportAction;
use Modules\AcademicReports\Application\Services\MonthlyReportScopeValidator;
use Modules\AcademicReports\Presentation\Filament\Resources\MonthlyReportResource;
use Shared\Support\BusinessRuleViolation;

final class CreateMonthlyReport extends CreateRecord
{
    protected static string $resource = MonthlyReportResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $organizationId = (string) data_get(auth()->user(), 'organization_id');
        $studentProfileId = (string) ($data['student_profile_id'] ?? '');
        $enrollmentId = (string) ($data['enrollment_id'] ?? '');

        try {
            app(MonthlyReportScopeValidator::class)->validate(
                $organizationId,
                $studentProfileId,
                $enrollmentId,
            );

            return app(DraftMonthlyReportAction::class)->execute(
                organizationId: $organizationId,
                studentProfileId: $studentProfileId,
                enrollmentId: $enrollmentId,
                periodYear: (int) ($data['period_year'] ?? 0),
                periodMonth: (int) ($data['period_month'] ?? 0),
                metrics: is_array($data['metrics'] ?? null) ? $data['metrics'] : [],
                supervisorSummary: filled($data['supervisor_summary'] ?? null)
                    ? (string) $data['supervisor_summary']
                    : null,
            );
        } catch (BusinessRuleViolation $violation) {
            $this->addError('data.student_profile_id', $violation->getMessage());
            $this->halt();

            throw $violation;
        }
    }
}
