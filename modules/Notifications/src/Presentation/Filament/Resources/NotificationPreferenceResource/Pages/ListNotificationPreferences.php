<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Filament\Resources\NotificationPreferenceResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Notifications\Presentation\Filament\Resources\NotificationPreferenceResource;

/**
 * صفحة فهرس NotificationPreferenceResource — كان المورد مُعلنًا في اللوحة بلا صفحات، فلا مسار له ولا
 * سبيل لفتحه رغم ظهوره في الشيفرة.
 */
final class ListNotificationPreferences extends ListRecords
{
    protected static string $resource = NotificationPreferenceResource::class;
}
