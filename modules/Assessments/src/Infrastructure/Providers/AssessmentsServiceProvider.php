<?php

declare(strict_types=1);

namespace Modules\Assessments\Infrastructure\Providers;

use Modules\Assessments\Application\Policies\AssessmentAttemptPolicy;
use Modules\Assessments\Application\Policies\AssessmentPolicy;
use Modules\Assessments\Application\Policies\QuestionPolicy;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
use Modules\Assessments\Domain\Models\Question;
use Shared\Module\BaseModuleServiceProvider;
use Shared\Support\DatabaseTransaction;
use Shared\Support\Transaction;

final class AssessmentsServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Assessments';
    }

    /**
     * أحداث الموديول ومستمعوها — لا مستمعين خارجيين بعد؛
     * بقية الموديولات (Discipline، Notifications، Reporting) تستمع
     * إلى أحداثنا من موديولاتها.
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
    protected function policies(): array
    {
        return [
            Assessment::class => AssessmentPolicy::class,
            Question::class => QuestionPolicy::class,
            AssessmentAttempt::class => AssessmentAttemptPolicy::class,
        ];
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
