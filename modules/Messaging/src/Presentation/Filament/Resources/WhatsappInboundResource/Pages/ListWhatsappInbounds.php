<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Filament\Resources\WhatsappInboundResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Messaging\Presentation\Filament\Resources\WhatsappInboundResource;

/**
 * صفحة فهرس WhatsappInboundResource — كان المورد مُعلنًا في اللوحة بلا صفحات، فلا مسار له ولا
 * سبيل لفتحه رغم ظهوره في الشيفرة.
 */
final class ListWhatsappInbounds extends ListRecords
{
    protected static string $resource = WhatsappInboundResource::class;
}
