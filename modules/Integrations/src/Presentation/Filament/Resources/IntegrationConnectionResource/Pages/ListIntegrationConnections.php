<?php

declare(strict_types=1);

namespace Modules\Integrations\Presentation\Filament\Resources\IntegrationConnectionResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Integrations\Presentation\Filament\Resources\IntegrationConnectionResource;

/**
 * صفحة فهرس IntegrationConnectionResource — كان المورد مُعلنًا في اللوحة بلا صفحات، فلا مسار له ولا
 * سبيل لفتحه رغم ظهوره في الشيفرة.
 */
final class ListIntegrationConnections extends ListRecords
{
    protected static string $resource = IntegrationConnectionResource::class;
}
