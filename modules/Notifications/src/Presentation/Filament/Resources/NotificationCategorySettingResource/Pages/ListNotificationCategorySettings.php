<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Filament\Resources\NotificationCategorySettingResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Notifications\Application\Services\CategorySettingsSynchronizer;
use Modules\Notifications\Presentation\Filament\Resources\NotificationCategorySettingResource;

final class ListNotificationCategorySettings extends ListRecords
{
    protected static string $resource = NotificationCategorySettingResource::class;

    /**
     * يضمن ظهور كل فئة معرّفة في config كصف قابل للتعديل — يُنشئ الناقص
     * بالافتراضي عند أول فتح، فيرى الأدمن قائمة كاملة لا فارغة.
     */
    public function mount(): void
    {
        parent::mount();

        $organizationId = (string) data_get(auth()->user(), 'organization_id');

        if ($organizationId !== '' && auth()->user()?->can('settings.manage')) {
            app(CategorySettingsSynchronizer::class)->ensureForOrganization($organizationId);
        }
    }
}
