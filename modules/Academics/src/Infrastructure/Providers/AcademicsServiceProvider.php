<?php

declare(strict_types=1);

namespace Modules\Academics\Infrastructure\Providers;

use Modules\Academics\Application\Policies\CoursePolicy;
use Modules\Academics\Application\Policies\LevelPolicy;
use Modules\Academics\Application\Policies\ProgramPolicy;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Shared\Module\BaseModuleServiceProvider;

final class AcademicsServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Academics';
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            Program::class => ProgramPolicy::class,
            Level::class => LevelPolicy::class,
            Course::class => CoursePolicy::class,
        ];
    }

    /**
     * لا أحداث خارجية يستهلكها الموديول بعد.
     *
     * @return array<class-string, list<class-string>>
     */
    protected function listeners(): array
    {
        return [];
    }

    /**
     * لا عقود عامة تحتاج ربطًا بعد — القراءة تتم عبر النماذج داخل الموديول
     * وعبر الأحداث للخارج.
     *
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [];
    }
}
