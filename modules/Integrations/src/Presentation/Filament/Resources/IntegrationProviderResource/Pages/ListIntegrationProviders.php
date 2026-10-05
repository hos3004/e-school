<?php

declare(strict_types=1);

namespace Modules\Integrations\Presentation\Filament\Resources\IntegrationProviderResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Integrations\Presentation\Filament\Resources\IntegrationProviderResource;

/**
 * صفحة فهرس IntegrationProviderResource — كان المورد مُعلنًا في اللوحة بلا صفحات، فلا مسار له ولا
 * سبيل لفتحه رغم ظهوره في الشيفرة.
 */
final class ListIntegrationProviders extends ListRecords
{
    protected static string $resource = IntegrationProviderResource::class;
}
