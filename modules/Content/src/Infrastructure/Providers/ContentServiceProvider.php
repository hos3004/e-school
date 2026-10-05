<?php

declare(strict_types=1);

namespace Modules\Content\Infrastructure\Providers;

use Modules\Content\Application\Policies\CourseMaterialPolicy;
use Modules\Content\Application\Queries\CourseMaterialLibraryQueryService;
use Modules\Content\Domain\Contracts\CourseMaterialLibraryQueries;
use Modules\Content\Domain\Models\CourseMaterial;
use Shared\Module\BaseModuleServiceProvider;

final class ContentServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Content';
    }

    /** @return array<class-string, class-string> */
    protected function policies(): array
    {
        return [CourseMaterial::class => CourseMaterialPolicy::class];
    }

    /** @return array<class-string, class-string> */
    protected function bindings(): array
    {
        return [CourseMaterialLibraryQueries::class => CourseMaterialLibraryQueryService::class];
    }
}
