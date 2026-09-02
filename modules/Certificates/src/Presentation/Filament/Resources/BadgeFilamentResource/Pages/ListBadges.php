<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Filament\Resources\BadgeFilamentResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Certificates\Presentation\Filament\Resources\BadgeFilamentResource;

/**
 * صفحة فهرس BadgeFilamentResource — كان المورد مُعلنًا في اللوحة بلا صفحات، فلا مسار له ولا
 * سبيل لفتحه رغم ظهوره في الشيفرة.
 */
final class ListBadges extends ListRecords
{
    protected static string $resource = BadgeFilamentResource::class;
}
