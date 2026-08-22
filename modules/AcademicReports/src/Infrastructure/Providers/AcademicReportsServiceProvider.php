<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Infrastructure\Providers;

use Modules\AcademicReports\Application\Policies\MonthlyReportPolicy;
use Modules\AcademicReports\Application\Policies\SessionReportPolicy;
use Modules\AcademicReports\Application\Policies\SessionReportStudentPolicy;
use Modules\AcademicReports\Domain\Models\MonthlyReport;
use Modules\AcademicReports\Domain\Models\SessionReport;
use Modules\AcademicReports\Domain\Models\SessionReportStudent;
use Shared\Module\BaseModuleServiceProvider;
use Shared\Support\DatabaseTransaction;
use Shared\Support\Transaction;

final class AcademicReportsServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'AcademicReports';
    }

    /**
     * ربط الموارد بسياساتها.
     *
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            SessionReport::class => SessionReportPolicy::class,
            SessionReportStudent::class => SessionReportStudentPolicy::class,
            MonthlyReport::class => MonthlyReportPolicy::class,
        ];
    }

    /**
     * لا مستمعون خارجيون بعد — أحداث الموديول تُنشر فقط.
     *
     * @return array<class-string, list<class-string>>
     */
    protected function listeners(): array
    {
        return [];
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [
            Transaction::class => DatabaseTransaction::class,
        ];
    }
}
