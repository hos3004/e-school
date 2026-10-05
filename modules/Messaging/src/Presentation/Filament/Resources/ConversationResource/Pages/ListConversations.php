<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Filament\Resources\ConversationResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Messaging\Presentation\Filament\Resources\ConversationResource;

/**
 * صفحة فهرس ConversationResource — كان المورد مُعلنًا في اللوحة بلا صفحات، فلا مسار له ولا
 * سبيل لفتحه رغم ظهوره في الشيفرة.
 */
final class ListConversations extends ListRecords
{
    protected static string $resource = ConversationResource::class;
}
