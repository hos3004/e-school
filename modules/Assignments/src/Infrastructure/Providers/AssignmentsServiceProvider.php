<?php

declare(strict_types=1);

namespace Modules\Assignments\Infrastructure\Providers;

use Modules\Assignments\Application\Policies\AssignmentPolicy;
use Modules\Assignments\Application\Policies\AssignmentSubmissionPolicy;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Assignments\Domain\Models\AssignmentSubmission;
use Shared\Module\BaseModuleServiceProvider;

final class AssignmentsServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Assignments';
    }

    protected function policies(): array
    {
        return [
            Assignment::class => AssignmentPolicy::class,
            AssignmentSubmission::class => AssignmentSubmissionPolicy::class,
        ];
    }
}
