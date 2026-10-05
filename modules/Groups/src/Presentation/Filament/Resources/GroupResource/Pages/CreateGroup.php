<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Filament\Resources\GroupResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Groups\Application\Actions\CreateGroupAction;
use Modules\Groups\Presentation\Filament\Resources\GroupResource;

final class CreateGroup extends CreateRecord
{
    protected static string $resource = GroupResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $organizationId = auth()->user()?->getAttribute('organization_id');
        abort_unless(is_string($organizationId) && $organizationId !== '', 403);
        $data['organization_id'] = $organizationId;

        return app(CreateGroupAction::class)->execute(
            $data,
            (string) auth()->id(),
            (string) $data['reason'],
        );
    }
}
