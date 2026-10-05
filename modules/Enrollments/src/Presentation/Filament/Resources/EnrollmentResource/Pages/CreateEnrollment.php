<?php

declare(strict_types=1);

namespace Modules\Enrollments\Presentation\Filament\Resources\EnrollmentResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Enrollments\Application\Actions\ApplyForEnrollmentAction;
use Modules\Enrollments\Presentation\Filament\Resources\EnrollmentResource;

final class CreateEnrollment extends CreateRecord
{
    protected static string $resource = EnrollmentResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        return app(ApplyForEnrollmentAction::class)->execute(
            organizationId: (string) auth()->user()?->getAttribute('organization_id'),
            studentProfileId: (string) $data['student_profile_id'],
            programId: (string) $data['program_id'],
            reason: (string) $data['reason'],
            currentLevelId: is_string($data['current_level_id'] ?? null) ? $data['current_level_id'] : null,
            actorId: (string) auth()->id(),
        );
    }

    protected function getCreatedNotificationTitle(): string
    {
        return __('enrollments::filament.notifications.created');
    }
}
