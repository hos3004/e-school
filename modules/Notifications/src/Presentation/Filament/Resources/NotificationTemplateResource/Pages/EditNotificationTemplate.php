<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Filament\Resources\NotificationTemplateResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Notifications\Presentation\Filament\Resources\NotificationTemplateResource;

final class EditNotificationTemplate extends EditRecord
{
    protected static string $resource = NotificationTemplateResource::class;

    /** @return list<Action> */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
