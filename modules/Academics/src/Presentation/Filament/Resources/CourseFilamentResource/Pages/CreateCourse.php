<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Filament\Resources\CourseFilamentResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Academics\Application\Actions\CreateCourseAction;
use Modules\Academics\Presentation\Filament\Resources\CourseFilamentResource;

final class CreateCourse extends CreateRecord
{
    protected static string $resource = CourseFilamentResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $organizationId = auth()->user()?->getAttribute('organization_id');
        abort_unless(is_string($organizationId) && $organizationId !== '', 403);
        $data['organization_id'] = $organizationId;

        return app(CreateCourseAction::class)->execute($data, (string) auth()->id(), (string) $data['reason']);
    }
}
