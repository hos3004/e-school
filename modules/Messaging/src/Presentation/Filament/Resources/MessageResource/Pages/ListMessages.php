<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Filament\Resources\MessageResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Messaging\Presentation\Filament\Resources\MessageResource;

/**
 * صفحة فهرس MessageResource — كان المورد مُعلنًا في اللوحة بلا صفحات، فلا مسار له ولا
 * سبيل لفتحه رغم ظهوره في الشيفرة.
 */
final class ListMessages extends ListRecords
{
    protected static string $resource = MessageResource::class;
}
