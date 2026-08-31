<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Filament\Resources\RoleResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\AccessControl\Application\Actions\SyncRolePermissionsAction;
use Modules\AccessControl\Application\Actions\UpdateRoleAction;
use Modules\AccessControl\Domain\Models\Role;
use Modules\AccessControl\Presentation\Filament\Resources\RoleResource;

final class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Role, 404);
        $organizationId = auth()->user()?->getAttribute('organization_id');
        abort_unless(is_string($organizationId) && $organizationId !== '', 403);

        $role = app(UpdateRoleAction::class)->execute(
            roleId: (string) $record->getKey(),
            name: isset($data['name']) ? (string) $data['name'] : null,
            actorId: (string) auth()->id(),
            scopeOrganizationId: $organizationId,
            reason: (string) $data['reason'],
        );

        app(SyncRolePermissionsAction::class)->execute(
            roleId: (string) $record->getKey(),
            permissionNames: array_values(array_map('strval', (array) ($data['permission_names'] ?? []))),
            actorId: (string) auth()->id(),
            organizationId: $organizationId,
            reason: (string) $data['reason'],
        );

        return $role;
    }
}
