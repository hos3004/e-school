<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Filament\Resources\BadgeAwardFilamentResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Certificates\Presentation\Filament\Resources\BadgeAwardFilamentResource;

/**
 * صفحة فهرس BadgeAwardFilamentResource — كان المورد مُعلنًا في اللوحة بلا صفحات، فلا مسار له ولا
 * سبيل لفتحه رغم ظهوره في الشيفرة.
 */
final class ListBadgeAwards extends ListRecords
{
    protected static string $resource = BadgeAwardFilamentResource::class;
}
