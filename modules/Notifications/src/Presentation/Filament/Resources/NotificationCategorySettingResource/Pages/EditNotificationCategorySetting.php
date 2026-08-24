<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Filament\Resources\NotificationCategorySettingResource\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Modules\Notifications\Presentation\Filament\Resources\NotificationCategorySettingResource;

final class EditNotificationCategorySetting extends EditRecord
{
    protected static string $resource = NotificationCategorySettingResource::class;

    /** @return list<Action> */
    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
