<?php

declare(strict_types=1);

namespace Modules\Reporting\Infrastructure\Providers;

use Modules\Attendance\Domain\Events\AttendanceConfirmed;
use Modules\Discipline\Domain\Events\ViolationRecorded;
use Modules\Enrollments\Domain\Events\EnrollmentFrozen;
use Modules\Reporting\Application\Listeners\ProjectDomainEventToDashboards;
use Modules\Reporting\Application\Policies\OrganizationSnapshotPolicy;
use Modules\Reporting\Application\Policies\ReportEventLogPolicy;
use Modules\Reporting\Application\Policies\StudentDashboardPolicy;
use Modules\Reporting\Application\Policies\TeacherDashboardPolicy;
use Modules\Reporting\Application\Queries\DashboardQueryService;
use Modules\Reporting\Application\Queries\OperationalReportQueryService;
use Modules\Reporting\Domain\Contracts\DashboardQuery;
use Modules\Reporting\Domain\Contracts\OperationalReportQuery;
use Modules\Reporting\Domain\Contracts\ReportPdfRenderer;
use Modules\Reporting\Domain\Models\OrganizationSnapshot;
use Modules\Reporting\Domain\Models\ReportEventLog;
use Modules\Reporting\Domain\Models\StudentDashboard;
use Modules\Reporting\Domain\Models\TeacherDashboard;
use Modules\Reporting\Infrastructure\Pdf\MpdfReportPdfRenderer;
use Modules\Sessions\Domain\Events\SessionCompleted;
use Modules\Sessions\Domain\Events\SessionNoShowRecorded;
use Shared\Module\BaseModuleServiceProvider;

/**
 * أحداث الموديولات الأخرى تُستهلك هنا عبر مستمع الإسقاط العام.
 *
 * التسجيل بأسماء أصناف الأحداث فقط — لا استيراد نماذج من موديول آخر،
 * وغياب أي موديول مصدر لا يكسر الإقلاع (التسجيل نصوص تُفعَّل عند الإرسال).
 */
final class ReportingServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Payroll موديول مختوم (`config/modules.php` → `sealed_domains`)، والقناة
     * الوحيدة المسموحة نحوه هي عقوده المعلنة. الاشتراك في حدث مجاله يتم هنا
     * باسمه نصًّا لا باستيراد صنفه: `Event::listen` يقبل الاسم كما هو، فيبقى
     * الإسقاط يعمل بلا اقتران بأصناف الموديول المختوم.
     */
    private const PAYROLL_ENTRY_RECORDED = 'Modules\\Payroll\\Domain\\Events\\PayrollEntryRecorded';

    protected function moduleName(): string
    {
        return 'Reporting';
    }

    /**
     * أحداث المصادر التي يُبنى عليها الإسقاط.
     *
     * @return array<string, list<class-string>>
     */
    protected function listeners(): array
    {
        $projection = ProjectDomainEventToDashboards::class;

        return [
            SessionCompleted::class => [$projection],
            SessionNoShowRecorded::class => [$projection],
            AttendanceConfirmed::class => [$projection],
            ViolationRecorded::class => [$projection],
            self::PAYROLL_ENTRY_RECORDED => [$projection],
            EnrollmentFrozen::class => [$projection],
        ];
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            StudentDashboard::class => StudentDashboardPolicy::class,
            TeacherDashboard::class => TeacherDashboardPolicy::class,
            OrganizationSnapshot::class => OrganizationSnapshotPolicy::class,
            ReportEventLog::class => ReportEventLogPolicy::class,
        ];
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [
            DashboardQuery::class => DashboardQueryService::class,
            OperationalReportQuery::class => OperationalReportQueryService::class,
            ReportPdfRenderer::class => MpdfReportPdfRenderer::class,
        ];
    }
}
