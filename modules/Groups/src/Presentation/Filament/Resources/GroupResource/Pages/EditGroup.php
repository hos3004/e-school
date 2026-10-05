<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Filament\Resources\GroupResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Groups\Application\Actions\UpdateGroupAction;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Presentation\Filament\Resources\GroupResource;

final class EditGroup extends EditRecord
{
    protected static string $resource = GroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Group, 404);

        return app(UpdateGroupAction::class)->execute(
            $record,
            $data,
            (string) auth()->id(),
            (string) $data['reason'],
        );
    }
}
