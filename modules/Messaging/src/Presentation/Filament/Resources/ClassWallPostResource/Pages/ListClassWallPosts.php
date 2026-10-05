<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Filament\Resources\ClassWallPostResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Messaging\Presentation\Filament\Resources\ClassWallPostResource;

/**
 * صفحة فهرس ClassWallPostResource — كان المورد مُعلنًا في اللوحة بلا صفحات، فلا مسار له ولا
 * سبيل لفتحه رغم ظهوره في الشيفرة.
 */
final class ListClassWallPosts extends ListRecords
{
    protected static string $resource = ClassWallPostResource::class;
}
