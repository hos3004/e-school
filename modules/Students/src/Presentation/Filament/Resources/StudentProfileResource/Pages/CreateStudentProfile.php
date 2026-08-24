<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources\StudentProfileResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Modules\Students\Presentation\Filament\Resources\StudentProfileResource;

final class CreateStudentProfile extends CreateRecord
{
    protected static string $resource = StudentProfileResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organization_id'] = (string) data_get(auth()->user(), 'organization_id');
        $data['student_code'] = 'STU-'.strtoupper(Str::random(8));

        return $data;
    }

    protected function getCreatedNotificationTitle(): string
    {
        return __('students::messages.student_registered');
    }
}
