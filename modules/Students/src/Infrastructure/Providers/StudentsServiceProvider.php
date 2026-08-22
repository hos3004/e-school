<?php

declare(strict_types=1);

namespace Modules\Students\Infrastructure\Providers;

use Modules\Students\Application\Actions\ArchiveStudentAction;
use Modules\Students\Application\Actions\RegisterStudentAction;
use Modules\Students\Application\Actions\RestoreStudentAction;
use Modules\Students\Application\Actions\UpdateStudentProfileAction;
use Modules\Students\Application\Policies\StudentProfilePolicy;
use Modules\Students\Domain\Events\StudentArchived;
use Modules\Students\Domain\Events\StudentRegistered;
use Modules\Students\Domain\Events\StudentRestored;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Module\BaseModuleServiceProvider;

final class StudentsServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Students';
    }

    /**
     * ربط أحداث الموديول بمستمعيها — المستمعون من هذا الموديول فقط؛
     * بقية الموديولات تسجل استماعها لدى مزوّديها هي.
     *
     * @return array<class-string, list<class-string>>
     */
    protected function listeners(): array
    {
        return [
            StudentRegistered::class => [],
            StudentRestored::class => [],
            StudentArchived::class => [],
        ];
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            StudentProfile::class => StudentProfilePolicy::class,
        ];
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [
            RegisterStudentAction::class => RegisterStudentAction::class,
            UpdateStudentProfileAction::class => UpdateStudentProfileAction::class,
            ArchiveStudentAction::class => ArchiveStudentAction::class,
            RestoreStudentAction::class => RestoreStudentAction::class,
        ];
    }
}
