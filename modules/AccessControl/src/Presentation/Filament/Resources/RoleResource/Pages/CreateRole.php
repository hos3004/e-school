<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Filament\Resources\RoleResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\AccessControl\Application\Actions\CreateRoleAction;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Presentation\Filament\Resources\RoleResource;

final class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $organizationId = auth()->user()?->getAttribute('organization_id');
        abort_unless(is_string($organizationId) && $organizationId !== '', 403);

        return app(CreateRoleAction::class)->execute(
            name: (string) $data['name'],
            guard: GuardName::from((string) $data['guard_name']),
            organizationId: $organizationId,
            actorId: (string) auth()->id(),
            reason: (string) $data['reason'],
        );
    }
}
