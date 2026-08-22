<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources\StudentProfileResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Students\Presentation\Filament\Resources\StudentProfileResource;

final class CreateStudentProfile extends CreateRecord
{
    protected static string $resource = StudentProfileResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('students::messages.student_registered');
    }
}
