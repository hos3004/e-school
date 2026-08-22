<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Filament\Resources\CourseFilamentResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Academics\Presentation\Filament\Resources\CourseFilamentResource;

final class CreateCourse extends CreateRecord
{
    protected static string $resource = CourseFilamentResource::class;
}
