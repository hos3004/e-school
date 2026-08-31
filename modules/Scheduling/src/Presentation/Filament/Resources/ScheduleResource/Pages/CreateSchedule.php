<?php

declare(strict_types=1);

namespace Modules\Scheduling\Presentation\Filament\Resources\ScheduleResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Scheduling\Application\Actions\CreateScheduleAction;
use Modules\Scheduling\Presentation\Filament\Resources\ScheduleResource;

final class CreateSchedule extends CreateRecord
{
    protected static string $resource = ScheduleResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $organizationId = auth()->user()?->getAttribute('organization_id');
        abort_unless(is_string($organizationId) && $organizationId !== '', 403);

        return app(CreateScheduleAction::class)->execute(
            $organizationId,
            $data,
            (string) auth()->id(),
            (string) $data['reason'],
        );
    }
}
