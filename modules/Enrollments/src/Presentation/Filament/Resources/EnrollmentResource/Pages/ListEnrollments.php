<?php

declare(strict_types=1);

namespace Modules\Enrollments\Presentation\Filament\Resources\EnrollmentResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Enrollments\Presentation\Filament\Resources\EnrollmentResource;

final class ListEnrollments extends ListRecords
{
    protected static string $resource = EnrollmentResource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
