<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Filament\Resources\Users\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Identity\Application\Actions\RegisterUser;
use Modules\Identity\Presentation\Filament\Resources\Users\UserResource;

final class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $organizationId = auth()->user()?->getAttribute('organization_id');
        abort_unless(is_string($organizationId) && $organizationId !== '', 403);

        $data['organization_id'] = $organizationId;

        return $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        return app(RegisterUser::class)->execute($data);
    }
}
