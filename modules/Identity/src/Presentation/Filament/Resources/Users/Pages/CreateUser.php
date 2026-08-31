<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Filament\Resources\Users\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Identity\Application\Actions\CreateManagedUserAction;
use Modules\Identity\Presentation\Filament\Resources\Users\UserResource;

final class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $organizationId = auth()->user()?->getAttribute('organization_id');
        abort_unless(is_string($organizationId) && $organizationId !== '', 403);

        return app(CreateManagedUserAction::class)->execute(
            data: $data,
            organizationId: $organizationId,
            actorId: (string) auth()->id(),
        );
    }
}
