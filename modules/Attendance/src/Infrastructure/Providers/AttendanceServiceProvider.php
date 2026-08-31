<?php

declare(strict_types=1);

namespace Modules\Attendance\Infrastructure\Providers;

use Modules\Attendance\Application\Policies\AttendancePolicy;
use Modules\Attendance\Application\Queries\AttendanceAdministrationQueryService;
use Modules\Attendance\Application\Queries\AttendanceOperationsQueryService;
use Modules\Attendance\Domain\Contracts\AttendanceAdministrationQueries;
use Modules\Attendance\Domain\Models\Attendance;
use Shared\Module\BaseModuleServiceProvider;

final class AttendanceServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Attendance';
    }

    /**
     * ربط الموارد بسياساتها.
     *
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            Attendance::class => AttendancePolicy::class,
        ];
    }

    /**
     * لا مستمعين داخليين حاليًا — أحداث الحضور تستهلكها موديولات
     * Discipline و Payroll و Audit، وهذا الموديول ناشر فقط.
     *
     * @return array<class-string, list<class-string>>
     */
    protected function listeners(): array
    {
        return [];
    }

    /**
     * ربط الـ Contracts بتنفيذاتها — Transaction مربوط عالميًا من AppServiceProvider.
     *
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [
            AttendanceAdministrationQueries::class => AttendanceAdministrationQueryService::class,
        ];
    }

    /** @return array<class-string, class-string> */
    protected function scopedBindings(): array
    {
        return [AttendanceOperationsQueryService::class => AttendanceOperationsQueryService::class];
    }
}
