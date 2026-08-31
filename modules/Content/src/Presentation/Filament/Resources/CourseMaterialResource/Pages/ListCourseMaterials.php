<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Filament\Resources\CourseMaterialResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Content\Presentation\Filament\Resources\CourseMaterialResource;

final class ListCourseMaterials extends ListRecords
{
    protected static string $resource = CourseMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
