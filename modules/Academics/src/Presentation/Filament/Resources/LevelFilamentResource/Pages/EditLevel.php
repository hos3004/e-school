<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Filament\Resources\LevelFilamentResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Academics\Application\Actions\UpdateLevelAction;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Presentation\Filament\Resources\LevelFilamentResource;

final class EditLevel extends EditRecord
{
    protected static string $resource = LevelFilamentResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Level, 404);

        return app(UpdateLevelAction::class)->execute($record, $data, (string) auth()->id(), (string) $data['reason']);
    }
}
