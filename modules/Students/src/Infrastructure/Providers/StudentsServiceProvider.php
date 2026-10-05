<?php

declare(strict_types=1);

namespace Modules\Students\Infrastructure\Providers;

use Modules\Students\Application\Actions\ArchiveStudentAction;
use Modules\Students\Application\Actions\CreateRegistrationApplicationAction;
use Modules\Students\Application\Actions\CreateStudentOnboardingAction;
use Modules\Students\Application\Actions\RestoreStudentAction;
use Modules\Students\Application\Actions\SubmitPublicRegistrationFormAction;
use Modules\Students\Application\Actions\UpdateStudentProfileAction;
use Modules\Students\Application\Policies\RegistrationApplicationPolicy;
use Modules\Students\Application\Policies\RegistrationFormPolicy;
use Modules\Students\Application\Policies\RegistrationQuestionPolicy;
use Modules\Students\Application\Policies\StudentProfilePolicy;
use Modules\Students\Application\Queries\StudentAdmissionQueryService;
use Modules\Students\Application\Queries\StudentDirectoryQueryService;
use Modules\Students\Application\Services\StudentPlacementService;
use Modules\Students\Domain\Contracts\StudentAdmissionQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;
use Modules\Students\Domain\Contracts\StudentPlacementGateway;
use Modules\Students\Domain\Events\RegistrationAccepted;
use Modules\Students\Domain\Events\RegistrationRejected;
use Modules\Students\Domain\Events\RegistrationSubmitted;
use Modules\Students\Domain\Events\StudentArchived;
use Modules\Students\Domain\Events\StudentAssignedToTeacher;
use Modules\Students\Domain\Events\StudentRegistered;
use Modules\Students\Domain\Events\StudentRestored;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\RegistrationForm;
use Modules\Students\Domain\Models\RegistrationQuestion;
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
            RegistrationSubmitted::class => [],
            RegistrationAccepted::class => [],
            RegistrationRejected::class => [],
            StudentAssignedToTeacher::class => [],
        ];
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            StudentProfile::class => StudentProfilePolicy::class,
            RegistrationApplication::class => RegistrationApplicationPolicy::class,
            RegistrationForm::class => RegistrationFormPolicy::class,
            RegistrationQuestion::class => RegistrationQuestionPolicy::class,
        ];
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [
            UpdateStudentProfileAction::class => UpdateStudentProfileAction::class,
            ArchiveStudentAction::class => ArchiveStudentAction::class,
            RestoreStudentAction::class => RestoreStudentAction::class,
            CreateRegistrationApplicationAction::class => CreateRegistrationApplicationAction::class,
            SubmitPublicRegistrationFormAction::class => SubmitPublicRegistrationFormAction::class,
            CreateStudentOnboardingAction::class => CreateStudentOnboardingAction::class,
            StudentAdmissionQueries::class => StudentAdmissionQueryService::class,
            StudentDirectoryQueries::class => StudentDirectoryQueryService::class,
            StudentPlacementGateway::class => StudentPlacementService::class,
        ];
    }
}
