<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Filament\Resources\Users\Pages;

use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Identity\Application\Actions\UpdateUserProfile;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Presentation\Filament\Resources\Users\UserResource;
use Shared\Filament\UserAvatarAction;

final class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();
        abort_unless($record instanceof User, 404);

        return [
            UserAvatarAction::make(
                (string) $record->organization_id,
                (string) $record->getKey(),
            )->visible(fn (): bool => (bool) auth()->user()?->can('update', $record)),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof User, 404);

        return app(UpdateUserProfile::class)->execute(
            $record,
            array_intersect_key($data, array_flip(['name', 'phone', 'locale', 'timezone'])),
        );
    }
}
